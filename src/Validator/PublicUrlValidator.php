<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class PublicUrlValidator extends ConstraintValidator
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /** Noms d'hôte réservés au réseau local. */
    private const FORBIDDEN_HOST_SUFFIXES = ['localhost', '.localhost', '.local', '.internal', '.lan'];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PublicUrl) {
            throw new UnexpectedTypeException($constraint, PublicUrl::class);
        }

        if (null === $value || '' === $value) {
            return; // NotBlank s'en charge
        }

        if (!\is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $parts = parse_url($value);
        if (false === $parts) {
            $this->context->buildViolation($constraint->invalidMessage)->addViolation();

            return;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if (!\in_array($scheme, self::ALLOWED_SCHEMES, true)
            || '' === $host || isset($parts['user']) || isset($parts['pass'])) {
            $this->context->buildViolation($constraint->invalidMessage)->addViolation();

            return;
        }

        $host = trim($host, '[]'); // IPv6 littérale : [::1]

        if ($this->isForbiddenHostname($host) || !$this->resolvesToPublicAddresses($host, $constraint->resolveDns)) {
            $this->context->buildViolation($constraint->privateMessage)->addViolation();
        }
    }

    private function isForbiddenHostname(string $host): bool
    {
        foreach (self::FORBIDDEN_HOST_SUFFIXES as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function resolvesToPublicAddresses(string $host, bool $resolveDns): bool
    {
        if (false !== filter_var($host, \FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        if (!$resolveDns) {
            return true;
        }

        // Un nom de domaine qui ne se résout pas n'est pas un risque SSRF : le service sera simplement "down".
        $ips = gethostbynamel($host);

        foreach ($ips ?: [] as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    public static function isPublicIp(string $ip): bool
    {
        // PRIVATE_SUBNETS couvre : 127/8, 10/8, 172.16/12, 192.168/16, 169.254/16, 0/8, ::1, fc00::/7, fe80::/10...
        return !IpUtils::checkIp($ip, IpUtils::PRIVATE_SUBNETS);
    }
}
