<?php

declare(strict_types=1);

namespace App\Tests;

use EMS\CommonBundle\Common\CoreApi\CoreApi;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CoreApiMock
{
    public static function mock(TestCase $test): MockObject
    {
        return $test->getMockBuilder(CoreApi::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();
    }
}
