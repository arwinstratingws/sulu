<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Media\PropertiesProvider;

use Imagine\Exception\InvalidArgumentException;
use Imagine\Exception\RuntimeException;
use Imagine\Image\ImagineInterface;
use Symfony\Component\HttpFoundation\File\File;

class ImagePropertiesProvider implements MediaPropertiesProviderInterface
{
    /**
     * @var ImagineInterface
     */
    private $imagine;

    public function __construct(ImagineInterface $imagine)
    {
        $this->imagine = $imagine;
    }

    public function provide(File $file): array
    {
        $mimeType = $file->getMimeType();

        if (!$mimeType || !\fnmatch('image/*', $mimeType)) {
            return [];
        }

        $properties = [];

        try {
            $image = $this->imagine->open($file->getPathname());
            $size = $image->getSize();
            $properties['width'] = $size->getWidth();
            $properties['height'] = $size->getHeight();
        } catch (InvalidArgumentException|RuntimeException $exception) {
            // @ignoreException
        }

        return $properties;
    }
}
