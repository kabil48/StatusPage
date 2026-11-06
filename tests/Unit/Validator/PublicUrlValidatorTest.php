<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\PublicUrl;
use App\Validator\PublicUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/** U-14 */
final class PublicUrlValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new PublicUrlValidator();
    }

    /** @return iterable<string, array{string}> */
    public static function privateUrls(): iterable
    {
        yield 'loopback' => ['http://127.0.0.1'];
        yield 'metadata cloud' => ['http://169.254.169.254/latest/meta-data'];
        yield 'réseau 10/8' => ['http://10.0.0.1'];
        yield 'réseau 192.168/16' => ['https://192.168.1.10:8443'];
        yield 'IPv6 loopback' => ['http://[::1]/'];
        yield 'localhost' => ['http://localhost:8000'];
    }

    #[DataProvider('privateUrls')]
    public function testPrivateAddressesAreRejected(string $url): void
    {
        $constraint = new PublicUrl(resolveDns: false);
        $this->validator->validate($url, $constraint);

        $this->buildViolation($constraint->privateMessage)->assertRaised();
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUrls(): iterable
    {
        yield 'ftp' => ['ftp://example.com/file'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'identifiants' => ['https://user:pass@example.com'];
        yield 'pas une URL' => ['not a url'];
    }

    #[DataProvider('invalidUrls')]
    public function testInvalidSchemesAreRejected(string $url): void
    {
        $constraint = new PublicUrl(resolveDns: false);
        $this->validator->validate($url, $constraint);

        $this->buildViolation($constraint->invalidMessage)->assertRaised();
    }

    public function testPublicUrlIsValid(): void
    {
        $this->validator->validate('https://example.com', new PublicUrl(resolveDns: false));

        $this->assertNoViolation();
    }
}
