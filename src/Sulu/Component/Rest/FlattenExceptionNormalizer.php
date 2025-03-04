<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Rest;

use Sulu\Component\Rest\Exception\DependantResourcesFoundExceptionInterface;
use Sulu\Component\Rest\Exception\ReferencingResourcesFoundExceptionInterface;
use Sulu\Component\Rest\Exception\TranslationErrorMessageExceptionInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal the following class is only for internal use don't use it in your project
 */
class FlattenExceptionNormalizer implements NormalizerInterface
{
    /**
     * @var NormalizerInterface
     */
    private $decoratedNormalizer;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        NormalizerInterface $decoratedNormalizer,
        TranslatorInterface $translator
    ) {
        $this->decoratedNormalizer = $decoratedNormalizer;
        $this->translator = $translator;
    }

    public function normalize($exception, $format = null, array $context = [])
    {
        $data = $this->decoratedNormalizer->normalize($exception, $format, $context);
        $data['code'] = $exception->getCode();

        $contextException = $context['exception'] ?? null;
        if ($contextException instanceof TranslationErrorMessageExceptionInterface) {
            // set error message to detail property of response to match rfc 7807
            $data['detail'] = $this->translator->trans(
                $contextException->getMessageTranslationKey(),
                $contextException->getMessageTranslationParameters(),
                'admin'
            );
        }

        if ($context['debug'] ?? false) {
            if ($exception instanceof FlattenException) {
                $errors = $exception->getAsString();
            } else {
                $errors = (string) $exception;
            }

            $data['errors'] = [$errors];
        }

        if ($contextException instanceof DependantResourcesFoundExceptionInterface) {
            $data['dependantResourcesCount'] = $contextException->getDependantResourcesCount();
            $data['dependantResourceBatches'] = $contextException->getDependantResourceBatches();
            $data['resource'] = $contextException->getResource();
        }

        if ($contextException instanceof ReferencingResourcesFoundExceptionInterface) {
            $data['referencingResourcesCount'] = $contextException->getReferencingResourcesCount();
            $data['referencingResources'] = $contextException->getReferencingResources();
            $data['resource'] = $contextException->getResource();
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->decoratedNormalizer->supportsNormalization($data, $format);
    }
}
