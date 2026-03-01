<?php

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes Doctrine Doctor analyzers that don't have configuration keys
 * in the bundle's Configuration tree and therefore cannot be disabled via YAML config.
 *
 * - TimestampableTraitAnalyzer: flags ALL datetime fields as timestamps; our public setters
 *   are required by external callers (controllers, services).
 * - PrimaryKeyStrategyAnalyzer: suggests UUID v7 for primary keys; changing PK types
 *   on existing entities with FK relationships would be too invasive.
 * - TimeZoneAnalyzer: reports false-positive timezone mismatch (PHP uses Europe/Berlin,
 *   MySQL reports equivalent +01:00 offset).
 * - MySQLPerformanceConfigAnalyzer: suggests innodb_flush_log_at_trx_commit = 2
 *   which requires MySQL server config change, not a code issue.
 */
class DisableDoctrineDoctorAnalyzersPass implements CompilerPassInterface
{
    private const ANALYZERS_TO_REMOVE = [
        'AhmedBhs\DoctrineDoctor\Analyzer\Integrity\TimestampableTraitAnalyzer',
        'AhmedBhs\DoctrineDoctor\Analyzer\Integrity\PrimaryKeyStrategyAnalyzer',
        'AhmedBhs\DoctrineDoctor\Analyzer\Configuration\TimeZoneAnalyzer',
        'AhmedBhs\DoctrineDoctor\Infrastructure\Strategy\MySQL\Analyzer\MySQLPerformanceConfigAnalyzer',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::ANALYZERS_TO_REMOVE as $analyzerClass) {
            if ($container->hasDefinition($analyzerClass)) {
                $container->removeDefinition($analyzerClass);
            }
        }
    }
}
