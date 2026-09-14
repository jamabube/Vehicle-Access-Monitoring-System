<?php

namespace App\Services;

use App\Models\CodeSetting;

class CodeGenerator
{
    /**
     * Generate the next code for a given entity type.
     *
     * @param string $entity The entity name (e.g. 'employees', 'visitors')
     * @return string The generated code (e.g. 'EMP-00001', 'VIS-00042')
     * @throws \RuntimeException if no code setting exists for the entity
     */
    public static function generate(string $entity): string
    {
        $setting = CodeSetting::forEntity($entity);

        if (!$setting) {
            throw new \RuntimeException("No code setting found for entity: {$entity}");
        }

        return $setting->generateNext();
    }
}
