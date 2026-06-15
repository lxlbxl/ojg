<?php
/**
 * ProtocolGeneratorFactory — routes condition string to the correct generator.
 *
 * Usage:
 *   $generator = ProtocolGeneratorFactory::for('acne');
 *   $pdfBinary = $generator->generate($assessment, $name, $email, $regionProfile);
 */

require_once __DIR__ . '/AbstractProtocolGenerator.php';
require_once __DIR__ . '/PcosProtocolGenerator.php';
require_once __DIR__ . '/AcneProtocolGenerator.php';
require_once __DIR__ . '/WeightProtocolGenerator.php';
require_once __DIR__ . '/MensProtocolGenerator.php';

class ProtocolGeneratorFactory
{
    private static array $map = [
        'pcos'   => PcosProtocolGenerator::class,
        'acne'   => AcneProtocolGenerator::class,
        'weight' => WeightProtocolGenerator::class,
        'mens'   => MensProtocolGenerator::class,
        // Aliases / legacy funnel names
        'men'         => MensProtocolGenerator::class,
        "men's"       => MensProtocolGenerator::class,
        'men_vitale'  => MensProtocolGenerator::class,
        'glowclear'   => AcneProtocolGenerator::class,
        'leanflow'    => WeightProtocolGenerator::class,
        'cyclesync'   => PcosProtocolGenerator::class,
        'vitale'      => MensProtocolGenerator::class,
    ];

    /**
     * Return the appropriate generator for the given condition string.
     * Normalises to lowercase and strips spaces/hyphens before lookup.
     *
     * @throws InvalidArgumentException for unknown conditions
     */
    public static function for(string $condition): AbstractProtocolGenerator
    {
        $key = strtolower(trim(str_replace(['-', ' ', "'"], ['', '', ''], $condition)));

        $class = self::$map[$key] ?? null;

        if (!$class) {
            error_log("[ProtocolGeneratorFactory] Unknown condition '$condition', defaulting to PCOS");
            $class = PcosProtocolGenerator::class;
        }

        return new $class();
    }

    /** List all registered condition keys. */
    public static function supported(): array
    {
        return array_keys(self::$map);
    }
}
