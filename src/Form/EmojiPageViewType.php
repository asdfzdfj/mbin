<?php

declare(strict_types=1);

namespace App\Form;

use App\PageView\EmojiPageView;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
                'choices' => $this->buildChoices($options),
                'placeholder' => 'filter.emoji.category.select',
            ])
        ;
    }

    private function buildChoices(array $options): array
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
