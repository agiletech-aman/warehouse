<?php

namespace App\Support;

class FnsDetectionLocation
{
    /**
     * Parse encoded locations such as "G3CB CAM1" into their display values.
     *
     * @return array{godown: string, compartment: string}|null
     */
    public static function fromCameraName(?string $cameraName): ?array
    {
        if (! preg_match('/^G(\d+)C([A-Z0-9]+)(?=\s|$)/i', trim((string) $cameraName), $matches)) {
            return null;
        }

        return [
            'godown' => 'Godown_' . $matches[1],
            'compartment' => 'Compartment_' . $matches[2],
        ];
    }
}
