<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Content\Tests\Unit\Types;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkItem;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderInterface;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderPoolInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Types\Link;

class LinkTest extends TestCase
{
    /**
     * @var Link
     */
    private $link;

    /**
     * @var ObjectProphecy|LinkProviderPoolInterface
     */
    private $providerPool;

    /**
     * @var ObjectProphecy|LinkProviderInterface
     */
    private $provider;

    /**
     * @var ObjectProphecy|PropertyInterface
     */
    private $property;

    public function setUp(): void
    {
        $this->providerPool = $this->prophesize(LinkProviderPoolInterface::class);
        $this->property = $this->prophesize(PropertyInterface::class);
        $this->provider = $this->prophesize(LinkProviderInterface::class);

        $this->link = new Link($this->providerPool->reveal());
    }

    public function testGetViewData(): void
    {
        $this->property->getValue()
            ->shouldBeCalled()
            ->willReturn([
                'href' => '123456',
                'provider' => 'pages',
                'locale' => 'de',
                'target' => 'testTarget',
                'anchor' => 'testAnchor',
            ]);

        $result = $this->link->getViewData($this->property->reveal());

        $this->assertSame([
            'provider' => 'pages',
            'locale' => 'de',
            'target' => 'testTarget',
        ], $result);
    }

    public function testGetViewDataWithoutTarget(): void
    {
        $this->property->getValue()
            ->shouldBeCalled()
            ->willReturn([
                'provider' => 'pages',
                'locale' => 'de',
            ]);

        $result = $this->link->getViewData($this->property->reveal());

        $this->assertSame([
            'provider' => 'pages',
            'locale' => 'de',
        ], $result);
    }

    public function testGetViewDataNull(): void
    {
        $this->property->getValue()
            ->shouldBeCalled()
            ->willReturn(null);

        $result = $this->link->getViewData($this->property->reveal());

        $this->assertSame([], $result);
    }

    public function testGetContentData(): void
    {
        $this->property->getValue()
            ->shouldBeCalled()
            ->willReturn([
                'href' => '123456',
                'provider' => 'pages',
                'locale' => 'de',
                'target' => 'testTarget',
                'anchor' => 'testAnchor',
            ]);

        $this->providerPool->getProvider(Argument::type('string'))
            ->shouldBeCalled()
            ->willReturn($this->provider->reveal());

        $linkItem = $this->prophesize(LinkItem::class);
        $linkItem->getUrl()
            ->shouldBeCalled()
            ->willReturn('/test');

        $this->provider->preload(['123456'], 'de', true)
            ->shouldBeCalled()
            ->willReturn([$linkItem]);

        $result = $this->link->getContentData($this->property->reveal());

        $this->assertSame('/test#testAnchor', $result);
    }

    public function testGetContentDataWithoutAnchor(): void
    {
        $this->property->getValue()
            ->shouldBeCalled()
            ->willReturn([
                'href' => '123456',
                'provider' => 'pages',
                'locale' => 'de',
                'target' => 'testTarget',
            ]);

        $this->providerPool->getProvider(Argument::type('string'))
            ->shouldBeCalled()
            ->willReturn($this->provider->reveal());

        $linkItem = $this->prophesize(LinkItem::class);
        $linkItem->getUrl()
            ->shouldBeCalled()
            ->willReturn('/test');

        $this->provider->preload(['123456'], 'de', true)
            ->shouldBeCalled()
            ->willReturn([$linkItem]);

        $result = $this->link->getContentData($this->property->reveal());

        $this->assertSame('/test', $result);
    }

    public function testGetContentDataWithoutProvider(): void
    {
        $this->property->getValue()
            ->shouldBeCalled()
            ->willReturn([
                'href' => '123456',
                'locale' => 'de',
                'target' => 'testTarget',
                'anchor' => 'testAnchor',
            ]);

        $result = $this->link->getContentData($this->property->reveal());

        $this->assertNull($result);
    }

    public function testGetContentDataExternalProvider(): void
    {
        $this->property->getValue()
            ->shouldBeCalled()
            ->willReturn([
                'href' => '/test/test2',
                'provider' => 'external',
                'locale' => 'de',
                'target' => 'testTarget',
            ]);

        $result = $this->link->getContentData($this->property->reveal());

        $this->assertSame('/test/test2', $result);
    }

    public function testGetContentDataInvalidHref(): void
    {
        $this->property->getValue()
            ->shouldBeCalled()
            ->willReturn([
                'href' => '123456',
                'provider' => 'pages',
                'locale' => 'de',
                'target' => 'testTarget',
                'anchor' => 'testAnchor',
            ]);

        $this->providerPool->getProvider(Argument::type('string'))
            ->shouldBeCalled()
            ->willReturn($this->provider->reveal());

        $this->provider->preload(['123456'], 'de', true)
            ->shouldBeCalled()
            ->willReturn([]);

        $result = $this->link->getContentData($this->property->reveal());

        $this->assertNull($result);
    }
}
