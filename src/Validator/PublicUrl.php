<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * URL http(s) pointant vers une adresse publique (protection SSRF, RG-CHECK-06).
 *
 * Seconde ligne de défense à l'exécution : NoPrivateNetworkHttpClient.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class PublicUrl extends Constraint
{
    public string $invalidMessage = 'Cette URL n\'est pas valide (seuls http:// et https:// sont acceptés).';
    public string $privateMessage = 'Cette URL pointe vers une adresse réseau privée ou locale, ce qui est interdit.';

    public function __construct(
        public bool $resolveDns = true,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
