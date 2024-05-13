<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub;

use App\ActivityPub\ActorHandle;
use PHPUnit\Framework\TestCase;

class ActorHandleTest extends TestCase
{
    /**
     * @dataProvider handleProvider()
     */
    public function testHandleIsRecognized(string $input, array $output): void
    {
        $this->assertNotNull(ActorHandle::parse($input));
    }

    /**
     * @dataProvider invalidHandleProvider()
     */
    public function testBareWordIsNotHandle(string $input, ?array $output): void
    {
        $this->assertNull(ActorHandle::parse($input));
    }

    /**
     * @dataProvider handleProvider()
     */
    public function testHandleIsParsedProperly(string $input, array $output): void
    {
        $handle = ActorHandle::parse($input);
        $this->assertEquals($output['prefix'], $handle->prefix);
        $this->assertEquals($output['name'], $handle->name);
        $this->assertEquals($output['host'], $handle->host);
        $this->assertEquals($output['port'], $handle->port);
    }

    /**
     * @dataProvider handleProvider()
     */
    public function testReconstructedHandleIsSameAsInput(string $input, array $output): void
    {
        $handle = ActorHandle::parse($input);
        $this->assertEquals($input, (string) $handle);
    }

    public static function handleProvider(): array
    {
        $handleSamples = [
            'user@mbin.instance' => [
                'prefix' => null,
                'name' => 'user',
                'host' => 'mbin.instance',
                'port' => null,
            ],
            '@someone-512@mbin.instance' => [
                'prefix' => '@',
                'name' => 'someone-512',
                'host' => 'mbin.instance',
                'port' => null,
            ],
            '!engineering@ds9.space' => [
                'prefix' => '!',
                'name' => 'engineering',
                'host' => 'ds9.space',
                'port' => null,
            ],
            '@leon@pink.brainrot.internal:11037' => [
                'prefix' => '@',
                'name' => 'leon',
                'host' => 'pink.brainrot.internal',
                'port' => 11037,
            ],
            '@localuser' => [
                'prefix' => '@',
                'name' => 'localuser',
                'host' => null,
                'port' => null,
            ],
            '!not-lemmy-group' => [
                'prefix' => '!',
                'name' => 'not-lemmy-group',
                'host' => null,
                'port' => null,
            ],
        ];

        return self::testdoxFormatter($handleSamples);
    }

    public static function invalidHandleProvider(): array
    {
        $handleSamples = [
            'bareword_handle' => null,
        ];

        return self::testdoxFormatter($handleSamples);
    }

    private static function testdoxFormatter(array $sample): array
    {
        $inputs = array_keys($sample);
        $outputs = array_values($sample);

        return array_combine(
            $inputs,
            array_map(fn ($input, $output) => [$input, $output], $inputs, $outputs)
        );
    }
}
