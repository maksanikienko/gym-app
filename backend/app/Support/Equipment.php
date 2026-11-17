<?php

namespace App\Support;

/** Equipment type of an exercise and the matching load step. */
class Equipment
{
    public const BARBELL = 'barbell';
    public const DUMBBELL = 'dumbbell';
    public const MACHINE = 'machine';
    public const CABLE = 'cable';
    public const BODYWEIGHT = 'bodyweight';
    public const OTHER = 'other';

    private const OVERRIDES = [
        'back-squat' => self::BARBELL, 'front-squat' => self::BARBELL, 'deadlift' => self::BARBELL,
        'romanian-deadlift' => self::BARBELL, 'deficit-deadlift' => self::BARBELL, 'reset-deadlift' => self::BARBELL,
        'stiff-leg-deadlift' => self::BARBELL, 'good-morning' => self::BARBELL, 'military-press' => self::BARBELL,
        'close-grip-bench-press' => self::BARBELL, 'decline-bench-press' => self::BARBELL, 'pendlay-row' => self::BARBELL,
        'seal-row' => self::BARBELL, 'ez-bar-curl' => self::BARBELL, 'reverse-grip-ez-bar-curl' => self::BARBELL,
        'ez-bar-skull-crusher' => self::BARBELL, 'preacher-curl' => self::BARBELL, 'california-press' => self::BARBELL,
        'chest-supported-t-bar-row' => self::MACHINE, 'seated-t-bar-row' => self::MACHINE, 'shrug' => self::BARBELL,
        'goblet-squat' => self::DUMBBELL, 'humble-row' => self::DUMBBELL, 'arnold-press' => self::DUMBBELL,
        'hammer-curl' => self::DUMBBELL, 'spider-curl' => self::DUMBBELL, 'chest-supported-dumbbell-row' => self::DUMBBELL,
        'leg-press' => self::MACHINE, 'single-leg-leg-press' => self::MACHINE, 'pec-deck' => self::MACHINE,
        'reverse-pec-deck' => self::MACHINE, 'isolateral-pulldown' => self::MACHINE, 'assisted-dip' => self::MACHINE,
        'assisted-pull-up' => self::MACHINE, 'reverse-grip-assisted-pull-up' => self::MACHINE,
        'lat-pulldown' => self::CABLE, 'reverse-grip-lat-pulldown' => self::CABLE, 'neutral-grip-pulldown' => self::CABLE,
        'wide-grip-lat-pulldown' => self::CABLE, 'single-arm-pulldown' => self::CABLE, '1-arm-lat-pull-in' => self::CABLE,
        'seated-face-pull' => self::CABLE, 'rope-face-pull' => self::CABLE, 'egyptian-lateral-raise' => self::CABLE,
        'tricep-pressdown' => self::CABLE, 'v-bar-pressdown' => self::CABLE, 'rope-triceps-pressdown' => self::CABLE,
        'pull-up' => self::BODYWEIGHT, 'chin-up' => self::BODYWEIGHT, 'wide-grip-pull-up' => self::BODYWEIGHT,
        'neutral-grip-pull-up' => self::BODYWEIGHT, 'dip' => self::BODYWEIGHT, 'push-up' => self::BODYWEIGHT,
        'plank' => self::BODYWEIGHT, 'crunch' => self::BODYWEIGHT, 'bicycle-crunch' => self::BODYWEIGHT,
        'hanging-leg-raise' => self::BODYWEIGHT, 'ab-wheel-rollout' => self::BODYWEIGHT, 'back-extension' => self::BODYWEIGHT,
        'round-back-hyperextension' => self::BODYWEIGHT, 'glute-ham-raise' => self::BODYWEIGHT, 'donkey-kickback' => self::BODYWEIGHT,
        'sliding-leg-curl' => self::BODYWEIGHT, 'swiss-ball-leg-curl' => self::BODYWEIGHT, 'lateral-band-walk' => self::BODYWEIGHT,
        'band-pull-apart' => self::BODYWEIGHT, 'weighted-pull-up' => self::BODYWEIGHT, 'weighted-dip' => self::BODYWEIGHT,
        'weak-point-upper' => self::OTHER, 'weak-point-lower' => self::OTHER,
    ];

    public static function guess(string $slug): string
    {
        if (isset(self::OVERRIDES[$slug])) {
            return self::OVERRIDES[$slug];
        }

        return match (true) {
            str_contains($slug, 'dumbbell') => self::DUMBBELL,
            str_contains($slug, 'barbell'), str_contains($slug, 'smith') => self::BARBELL,
            str_contains($slug, 'cable'), str_contains($slug, 'rope') => self::CABLE,
            str_contains($slug, 'machine'), str_contains($slug, 'leg-curl'), str_contains($slug, 'leg-extension'),
            str_contains($slug, 'calf-raise'), str_contains($slug, 'abduction') => self::MACHINE,
            default => self::OTHER,
        };
    }

    /** Smallest sensible load change in kg. */
    public static function step(?string $equipment, float $weight = 0): float
    {
        return match ($equipment) {
            self::DUMBBELL => $weight < 10 ? 1.0 : 2.0,
            self::BODYWEIGHT => 2.5, // added weight
            default => 2.5,
        };
    }

    public static function round(float $weight, ?string $equipment, string $mode = 'nearest'): float
    {
        $step = self::step($equipment, $weight);
        $n = $weight / $step;
        $n = match ($mode) { 'down' => floor($n + 1e-9), 'up' => ceil($n - 1e-9), default => round($n) };
        $w = $n * $step;
        if ($equipment === self::BARBELL && $w > 0 && $w < 20) {
            $w = 20.0; // empty Olympic bar
        }

        return round(max(0, $w), 2);
    }
}
