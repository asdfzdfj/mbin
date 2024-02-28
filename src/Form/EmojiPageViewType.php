<?php

declare(strict_types=1);

namespace App\Form;

use App\PageView\EmojiPageView;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
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
                'empty_data' => null,
                'choices' => $this->buildCategoryChoices($options),
                // @todo find a better way to do partial label translation rather than suppressing it
                // by supplying bogus translation domain 'none'
                'choice_label' => fn ($c, $k, $v) => new TranslatableMessage(
                    $k, [], str_starts_with($k, 'filter.emoji') ? 'messages' : 'none'
                ),
                'placeholder' => 'filter.emoji.category.select',
            ])
        ;
    }

    private function buildCategoryChoices(array $options): array
    {
        $categories = $options['categories'];

        return array_merge(
            ['filter.emoji.category.uncategorized' => EmojiPageView::CATEGORY_UNCATEGORIZED],
            array_combine($categories, $categories),
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
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
