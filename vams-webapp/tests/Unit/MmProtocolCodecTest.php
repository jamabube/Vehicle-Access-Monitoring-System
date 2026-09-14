<?php

namespace Tests\Unit;

use App\Services\Rfid\MmProtocolCodec;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Framing rules are checked against the worked examples printed in the vendor
 * manual ("MM Version Reader Control Protocol v1.2", §2.3 and §4.1), so a
 * regression here shows up as a disagreement with the hardware's own docs
 * rather than with our assumptions about them.
 */
class MmProtocolCodecTest extends TestCase
{
    private MmProtocolCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codec = new MmProtocolCodec;
    }

    /** Convenience: "7C FF FF" style hex to raw bytes. */
    private function bytes(string $hex): string
    {
        return hex2bin(str_replace(' ', '', $hex));
    }

    #[Test]
    public function it_computes_the_checksum_from_the_manuals_worked_example(): void
    {
        // §2.3: "CC 02 01 B1 22 04 BB 12 02 03 88" — the trailing 88 is CHKSUM.
        $frame = $this->bytes('CC 02 01 B1 22 04 BB 12 02 03');

        $this->assertSame(0x88, $this->codec->checksum($frame));
    }

    #[Test]
    public function it_builds_the_inventory_command_frame(): void
    {
        // §4.1.1: HEAD 7C, ADDR FFFF, CID1 20, CID2 00, LENGTH 00.
        $this->assertSame(
            '7CFFFF20000066',
            strtoupper(bin2hex($this->codec->inventoryCommand()))
        );
    }

    #[Test]
    public function it_extracts_a_tag_report_frame_from_a_buffer(): void
    {
        $buffer = $this->tagReportFrame();

        $frames = $this->codec->extractFrames($buffer);

        $this->assertCount(1, $frames);
        $this->assertSame('', $buffer, 'A fully consumed frame should leave the buffer empty.');
        $this->assertSame(MmProtocolCodec::SOI_RESPONSE, $frames[0]['soi']);
        $this->assertSame(0xFFFF, $frames[0]['address']);
        $this->assertSame(MmProtocolCodec::CID1_READ_UII, $frames[0]['cid1']);
        $this->assertSame(MmProtocolCodec::RTN_TAG_REPORT, $frames[0]['rtn']);
    }

    #[Test]
    public function it_decodes_the_manuals_tag_report_example(): void
    {
        // §4.1.2: ANT=0x00, PC=0x3000, EPC=E2003411B802011383258566, RSSI=0xC9.
        $buffer = $this->tagReportFrame();
        $frames = $this->codec->extractFrames($buffer);

        $tag = $this->codec->decodeTagReport($frames[0]);

        $this->assertNotNull($tag);
        $this->assertSame(0, $tag['antenna']);
        $this->assertSame('3000', $tag['pc']);
        $this->assertSame('E2003411B802011383258566', $tag['epc']);
        $this->assertSame(-55, $tag['rssi'], '0xC9 should be read as a signed dBm value.');
    }

    #[Test]
    public function it_leaves_a_partial_frame_in_the_buffer_for_the_next_read(): void
    {
        $whole = $this->tagReportFrame();
        $buffer = substr($whole, 0, 10);

        $this->assertSame([], $this->codec->extractFrames($buffer));
        $this->assertSame(10, strlen($buffer), 'A partial frame must be kept, not discarded.');

        // The rest of the frame arrives on the next read.
        $buffer .= substr($whole, 10);

        $this->assertCount(1, $this->codec->extractFrames($buffer));
    }

    #[Test]
    public function it_resynchronises_past_leading_noise(): void
    {
        $buffer = $this->bytes('00 11 22').$this->tagReportFrame();

        $frames = $this->codec->extractFrames($buffer);

        $this->assertCount(1, $frames);
        $this->assertSame('E2003411B802011383258566', $this->codec->decodeTagReport($frames[0])['epc']);
    }

    #[Test]
    public function it_rejects_a_frame_whose_checksum_is_wrong(): void
    {
        $corrupted = substr($this->tagReportFrame(), 0, -1).chr(0x00);

        $this->assertSame([], $this->codec->extractFrames($corrupted));
    }

    #[Test]
    public function it_extracts_several_frames_from_one_read(): void
    {
        $buffer = $this->tagReportFrame().$this->tagReportFrame().$this->tagReportFrame();

        $this->assertCount(3, $this->codec->extractFrames($buffer));
    }

    #[Test]
    public function it_treats_an_auto_pushed_report_as_a_tag_read(): void
    {
        // A reader in active mode pushes tag frames with RTN 0x05 (§4.1).
        $info = $this->bytes('00 30 00 E2 00 34 11 B8 02 01 13 83 25 85 66 C9');
        $header = $this->bytes('CC FF FF 20 05').chr(strlen($info)).$info;
        $buffer = $header.chr($this->codec->checksum($header));

        $frames = $this->codec->extractFrames($buffer);

        $this->assertTrue($this->codec->isTagReport($frames[0]));
        $this->assertSame('E2003411B802011383258566', $this->codec->decodeTagReport($frames[0])['epc']);
    }

    #[Test]
    public function it_does_not_treat_an_inventory_summary_as_a_tag_read(): void
    {
        // §4.1.3: RTN 0x00 carries ANT/STC/RTC counters, not an EPC.
        $info = $this->bytes('00 27 27');
        $header = $this->bytes('CC FF FF 20 00').chr(strlen($info)).$info;
        $buffer = $header.chr($this->codec->checksum($header));

        $frames = $this->codec->extractFrames($buffer);

        $this->assertFalse($this->codec->isTagReport($frames[0]));
        $this->assertNull($this->codec->decodeTagReport($frames[0]));
    }

    /**
     * The tag-report frame from §4.1.2, checksum computed by the codec itself
     * (the manual prints the checksum only as "0xNN").
     */
    private function tagReportFrame(): string
    {
        $info = $this->bytes('00 30 00 E2 00 34 11 B8 02 01 13 83 25 85 66 C9');
        $header = $this->bytes('CC FF FF 20 02').chr(strlen($info)).$info;

        return $header.chr($this->codec->checksum($header));
    }
}
