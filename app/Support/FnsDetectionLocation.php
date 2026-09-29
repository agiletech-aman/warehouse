<?php

namespace App\Support;

class FnsDetectionLocation
{
    /**
     * Parse a camera name into the location values stored in the database.
     *
     * @return array{godown: string, compartment: string|null}|null
     */
    public static function fromCameraName(?string $cameraName): ?array
    {
        $cameraName = trim((string) $cameraName);

        if (preg_match('/^G(\d+)C([A-Z0-9]+)(?=\s|$)/i', $cameraName, $matches)) {
            return [
                'godown' => 'Godown_' . $matches[1],
                'compartment' => 'Compartment_' . $matches[2],
            ];
        }

        if (! preg_match('/^Godown[\s_-]*(\d+)(?=\D|$)/i', $cameraName, $matches)) {
            return null;
        }

        $compartment = preg_match('/Compartment[\s_-]*([A-Z0-9]+)/i', $cameraName, $compartmentMatches)
            ? 'Compartment_' . $compartmentMatches[1]
            : null;

        return [
            'godown' => 'Godown_' . $matches[1],
            'compartment' => $compartment,
        ];
    }
}
