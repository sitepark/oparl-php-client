<?php

declare(strict_types=1);

namespace SP\OparlClient\Test\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SP\OparlClient\Core\OparlError;
use SP\OparlClient\Test\Fixture\Json;

#[CoversClass(OparlError::class)]
final class OparlErrorTest extends TestCase
{
    public function testRecognizesErrorTypesOfOparl10And11(): void
    {
        $this->assertTrue(OparlError::isErrorType(OparlError::TYPE));
        $this->assertTrue(OparlError::isErrorType('https://schema.oparl.org/1.0/Error'));
        $this->assertTrue(OparlError::isErrorType(' http://schema.oparl.org/1.1/Error/ '));
        $this->assertFalse(OparlError::isErrorType(null));
        $this->assertFalse(OparlError::isErrorType('https://schema.oparl.org/1.1/Body'));
        $this->assertFalse(OparlError::isErrorType('https://schema.oparl.org/1.1/Error/x'));
    }

    public function testReadsAndWritesErrorObject(): void
    {
        $json = '{"type":"https://schema.oparl.org/1.1/Error","message":"Nicht gefunden","debug":"id 1",'
            . '"Hersteller:code":17}';

        $error = Json::map($json, OparlError::class);

        $this->assertSame('https://schema.oparl.org/1.1/Error', $error->getType());
        $this->assertSame('Nicht gefunden', $error->getMessage());
        $this->assertSame('id 1', $error->getDebug());
        $this->assertSame(17, $error->getAdditionalProperty('Hersteller:code'));
        $this->assertSame($json, json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
