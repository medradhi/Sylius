<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\CoreBundle\Form\Type\ChannelPricing;

use Sylius\Bundle\CoreBundle\Form\Type\Product\ChannelPricingType;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricing;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Mateusz Zalewski <mateusz.zalewski@lakion.com>
 */
final class ChannelPricingsType extends AbstractType implements EventSubscriberInterface
{
    /**
     * @var ChannelRepositoryInterface
     */
    private $channelRepository;

    /**
     * @param ChannelRepositoryInterface $channelRepository
     */
    public function __construct(ChannelRepositoryInterface $channelRepository)
    {
        $this->channelRepository = $channelRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber($this);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::SUBMIT => 'submit',
        ];
    }

    /**
     * @param FormEvent $event
     */
    public function preSetData(FormEvent $event)
    {
        $form = $event->getForm();
        $allChannels = $this->channelRepository->findAll();

        /** @var FormInterface $children */
        foreach ($this->getChannelCodesToDelete($form, $allChannels) as $channelCode) {
            $form->remove($channelCode);
        }

        /** @var ChannelInterface $channel */
        foreach ($allChannels as $channel) {
            if ($form->has($channel->getCode())) {
                continue;
            }

            $form->add($channel->getCode(), ChannelPricingType::class, [
                'channel' => $channel,
            ]);
        }
    }

    /**
     * @param FormEvent $event
     */
    public function submit(FormEvent $event)
    {
        /** @var ChannelPricing[] $channelPricings */
        $channelPricings = $event->getData();
        $variant = $event->getForm()->getParent()->getData();

        foreach ($channelPricings as $channelCode => $channelPricing) {
            if (null === $channelPricing->getPrice()) {
                unset($channelPricings[$channelCode]);

                continue;
            }

            $channelPricing->setChannelCode($channelCode);
            $channelPricing->setProductVariant($variant);
        }

        $event->setData($channelPricings);
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('entry_type', ChannelPricingType::class);
        $resolver->setDefault('error_bubbling', false);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return CollectionType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'sylius_channel_pricings';
    }

    /**
     * @param FormInterface $form
     * @param array $allChannels
     *
     * @return array
     */
    private function getChannelCodesToDelete(FormInterface $form, array $allChannels)
    {
        $currentChannelCodes = array_map(function (FormInterface $child) {
            return $child->getName();
        }, iterator_to_array($form));
        $allChannelCodes = array_map(function (ChannelInterface $channel) {
            return $channel->getCode();
        }, $allChannels);

        return array_diff($currentChannelCodes, $allChannelCodes);
    }
}
