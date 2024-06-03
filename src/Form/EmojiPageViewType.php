<?php

declare(strict_types=1);

namespace App\Form;

use App\PageView\EmojiPageView;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class EmojiPageViewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('query', TextType::class, [
                'attr' => [
                    'placeholder' => 'type_search_term',
                ],
                'required' => false,
            ])
            ->add('category', ChoiceType::class, [
                'required' => false,
                'choices' => $this->buildCategoryChoices($options),
                'choice_label' => $this->choiceLabelFunction(),
                'placeholder' => 'filter.emoji.category.select',
                'preferred_choices' => [EmojiPageView::CATEGORY_UNCATEGORIZED],
                'autocomplete' => true,
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $options = $form->getConfig()->getOptions();

            if ($options['domains']) {
                $form->add('domain', ChoiceType::class, [
                    'required' => false,
                    'choices' => $this->buildDomainChoices($options),
                    'choice_label' => $this->choiceLabelFunction(),
                    'placeholder' => 'filter.emoji.domain.select',
                    'preferred_choices' => [EmojiPageView::DOMAIN_LOCAL],
                    'autocomplete' => true,
                ]);
            }
        });
    }

    // @todo find a better way to do partial label translation rather than suppressing it
    // by supplying bogus translation domain 'none'
    private function choiceLabelFunction(): callable
    {
        return fn ($c, $k, $v) => new TranslatableMessage(
            $k, [], str_starts_with($k, 'filter.emoji') ? 'messages' : 'none'
        );
    }

    private function buildCategoryChoices(array $options): array
    {
        $categories = $options['categories'];

        return array_merge(
            ['filter.emoji.category.uncategorized' => EmojiPageView::CATEGORY_UNCATEGORIZED],
            array_combine($categories, $categories),
        );
    }

    private function buildDomainChoices(array $options): array
    {
        $domains = $options['domains'];

        return array_merge(
            ['filter.emoji.domain.local' => EmojiPageView::DOMAIN_LOCAL],
            array_combine($domains, $domains),
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => EmojiPageView::class,
                'csrf_protection' => false,
                'method' => 'GET',
                'categories' => [],
                'domains' => [],
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
