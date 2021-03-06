<?php

/*
 * This file is part of the Glavweb UploaderDropzoneBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\UploaderDropzoneBundle\Form;

use Doctrine\Common\Collections\Collection;
use Glavweb\UploaderBundle\Model\MediaInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * Class AbstractMediaCollectionType
 *
 * @package Glavweb\UploaderDropzoneBundle
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class AbstractMediaCollectionType extends AbstractMediaType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function ($medias) {
                return $medias;
            },

            function ($mediaDataJson) {
                $mediaData = json_decode($mediaDataJson, true);
                $medias = $this->getMedias($mediaData['medias']);

                $mediaIds = array_map(function (MediaInterface $media) {
                    return $media->getId();
                }, $medias);

                $uploadedMedias = $this->uploaderManager->handleUpload($mediaData['request_id']);
                $uploadedMedias = array_filter($uploadedMedias, function ($uploadedMedia) use ($mediaIds) {
                    return !in_array($uploadedMedia->getId(), $mediaIds);
                });

                if ($uploadedMedias) {
                    $medias = array_merge($medias, $uploadedMedias);
                }

                $position = 0;
                foreach ($medias as $media) {
                    $media->setPosition($position);
                    $position++;
                }

                return $this->filterRemovedMedias($medias);
            }
        ));
    }

    /**
     * @param FormView $view
     * @param FormInterface $form
     * @param array $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $inValue = $view->vars['value'];
        $medias  = [];
        if ($inValue instanceof Collection) {
            $medias = $inValue->toArray();

        } elseif (is_array($inValue)) {
            $medias = $inValue;
        }

        $structuredMedias = $this->mediaStructure->getStructure($medias, $options['thumbnail_filter'], true);
        $mediaSecuredIds  = array_map(function (array $structuredMedia) {
            return $structuredMedia['id'];
        }, $structuredMedias);

        $view->vars['value'] = $structuredMedias;

        // @todo rename to "value"
        $view->vars['mediaData'] = [
            'medias'     => $mediaSecuredIds,
            'request_id' => $view->vars['requestId']
        ];
    }

    /**
     * @param array $medias
     * @return array
     */
    private function filterRemovedMedias(array $medias)
    {
        return array_filter($medias, function (MediaInterface $media) {
            return $media->getId();
        });
    }
}
