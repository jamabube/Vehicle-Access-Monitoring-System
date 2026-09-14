<?php

namespace App\Services\Rfid;

/**
 * Codec for the S4A UHF-202415 reader's native binary link protocol
 * ("MM Version Reader Control Protocol v1.2", bundled at
 * `rfid reader sdk/sdk_UHF Reader/900MHz UHF Reader SDK(MM)/Document(文档)/`).
 *
 * Frame layout (protocol §2.2, Table 2-1):
 *
 *   SOI | ADDR(LSB) | ADDR(MSB) | CID1 | CID2/RTN | LENGTH | INFO[LENGTH] | CHKSUM
 *    1  |     1     |     1     |  1   |    1     |    1   |    LENGTH    |   1
 *
 *   SOI    0x7C on a command (host -> reader), 0xCC on a response.
 *   ADDR   Device address, little-endian. 0xFFFF is the public/broadcast address.
 *   CID1   What the frame is about (0x20 = Read Type C UII).
 *   CID2   Action on a command (0x00 senior / 0x31 set / 0x32 get);
 *          on a response this slot carries RTN instead (Table 2-3).
 *   CHKSUM Two's complement of the 8-bit sum of every preceding byte (§2.3).
 *
 * Pure and dependency-free so the framing rules can be unit-tested against the
 * worked examples printed in the vendor manual, per context/RULES.md §4.
 */
class MmProtocolCodec
{
    /** Start-of-information byte for a host -> reader command. */
    public const SOI_COMMAND = 0x7C;

    /** Start-of-information byte for a reader -> host response. */
    public const SOI_RESPONSE = 0xCC;

    /** Public/broadcast device address (protocol §2.2, ADR = FFFFH). */
    public const ADDRESS_PUBLIC = 0xFFFF;

    /** CID1 for "Read Type C UII" — the inventory/tag-read family (§4.1). */
    public const CID1_READ_UII = 0x20;

    /** CID2 for a "senior" (plain action) command (Table 3-2). */
    public const CID2_SENIOR = 0x00;

    /** RTN 0x00 — command succeeded; INFO carries the inventory summary. */
    public const RTN_SUCCEED = 0x00;

    /** RTN 0x02 — a tag report, one frame per tag found (§4.1.2). */
    public const RTN_TAG_REPORT = 0x02;

    /** RTN 0x05 — an unsolicited tag report pushed by a reader in active mode. */
    public const RTN_AUTO_REPORT = 0x05;

    /** Bytes of overhead a frame carries on top of its INFO payload. */
    private const FRAME_OVERHEAD = 7;

    /**
     * Two's-complement checksum over every byte preceding CHKSUM (§2.3).
     */
    public function checksum(string $bytes): int
    {
        $sum = 0;

        for ($i = 0, $len = strlen($bytes); $i < $len; $i++) {
            $sum = ($sum + ord($bytes[$i])) & 0xFF;
        }

        return ((~$sum) + 1) & 0xFF;
    }

    /**
     * Build a complete command frame, checksum included.
     */
    public function buildCommand(int $cid1, int $cid2, string $info = '', int $address = self::ADDRESS_PUBLIC): string
    {
        $frame = chr(self::SOI_COMMAND)
            .chr($address & 0xFF)
            .chr(($address >> 8) & 0xFF)
            .chr($cid1 & 0xFF)
            .chr($cid2 & 0xFF)
            .chr(strlen($info) & 0xFF)
            .$info;

        return $frame.chr($this->checksum($frame));
    }

    /**
     * The "Read Type C UII" inventory command (§4.1.1) — asks the reader to
     * report every Gen2 tag currently in its antenna field.
     *
     * Wire form: 7C FF FF 20 00 00 66
     */
    public function inventoryCommand(int $address = self::ADDRESS_PUBLIC): string
    {
        return $this->buildCommand(self::CID1_READ_UII, self::CID2_SENIOR, '', $address);
    }

    /**
     * Pull every complete, checksum-valid frame out of a rolling read buffer.
     *
     * Anything that cannot start a valid frame is discarded a byte at a time,
     * so the parser resynchronises after line noise or a partially-read frame
     * rather than wedging. `$buffer` is advanced past whatever was consumed;
     * a trailing partial frame is left in place for the next read.
     *
     * @return list<array{soi: int, address: int, cid1: int, rtn: int, info: string, raw: string}>
     */
    public function extractFrames(string &$buffer): array
    {
        $frames = [];

        while (($length = strlen($buffer)) > 0) {
            // Resync: drop leading bytes until the buffer starts with a SOI.
            $soi = ord($buffer[0]);
            if ($soi !== self::SOI_RESPONSE && $soi !== self::SOI_COMMAND) {
                $buffer = substr($buffer, 1);

                continue;
            }

            // Need the header through LENGTH before the full size is known.
            if ($length < 6) {
                break;
            }

            $infoLength = ord($buffer[5]);
            $frameLength = self::FRAME_OVERHEAD + $infoLength;

            if ($length < $frameLength) {
                break;
            }

            $raw = substr($buffer, 0, $frameLength);
            $expected = $this->checksum(substr($raw, 0, $frameLength - 1));

            if ($expected !== ord($raw[$frameLength - 1])) {
                // Bad checksum — this was not a real frame boundary. Skip one
                // byte and let the scan re-latch onto the next candidate SOI.
                $buffer = substr($buffer, 1);

                continue;
            }

            $frames[] = [
                'soi' => $soi,
                'address' => ord($raw[1]) | (ord($raw[2]) << 8),
                'cid1' => ord($raw[3]),
                'rtn' => ord($raw[4]),
                'info' => substr($raw, 6, $infoLength),
                'raw' => $raw,
            ];

            $buffer = substr($buffer, $frameLength);
        }

        return $frames;
    }

    /**
     * True if this frame is a tag report (solicited or auto-pushed).
     *
     * @param  array{cid1: int, rtn: int, info: string}  $frame
     */
    public function isTagReport(array $frame): bool
    {
        return $frame['cid1'] === self::CID1_READ_UII
            && in_array($frame['rtn'], [self::RTN_TAG_REPORT, self::RTN_AUTO_REPORT], true);
    }

    /**
     * Decode a tag report's INFO payload (§4.1.2):
     *
     *   ANT (1) | PC (2) | EPC (LENGTH - 4) | RSSI (1)
     *
     * RSSI is reported as a raw byte and read back as a signed value, which is
     * the usual convention for these modules — the manual's worked example of
     * 0xC9 decodes to -55 dBm, a plausible near-field reading.
     *
     * @param  array{cid1: int, rtn: int, info: string}  $frame
     * @return array{antenna: int, pc: string, epc: string, rssi: int}|null
     */
    public function decodeTagReport(array $frame): ?array
    {
        if (! $this->isTagReport($frame)) {
            return null;
        }

        $info = $frame['info'];
        $epcLength = strlen($info) - 4;

        // ANT + PC(2) + RSSI leaves nothing for the EPC below 5 bytes of INFO.
        if ($epcLength < 1) {
            return null;
        }

        $rssi = ord($info[strlen($info) - 1]);

        return [
            'antenna' => ord($info[0]),
            'pc' => strtoupper(bin2hex(substr($info, 1, 2))),
            'epc' => strtoupper(bin2hex(substr($info, 3, $epcLength))),
            'rssi' => $rssi > 127 ? $rssi - 256 : $rssi,
        ];
    }
}
