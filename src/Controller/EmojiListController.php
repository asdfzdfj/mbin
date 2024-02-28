<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\EmojiPageViewType;
use App\PageView\EmojiPageView;
use App\Repository\EmojiRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EmojiListController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EmojiRepository $repository,
    ) {
    }

    public function __invoke(?string $category, Request $request): Response
    {
        $criteria = new EmojiPageView(
            $this->getPageNb($request),
            $category
        );

        $form = $this->createForm(EmojiPageViewType::class, $criteria, [
            'categories' => $this->repository->getCategories(),
        ]);

        $form->handleRequest($request);

        $emojis = $this->repository->findPaginated($criteria);

        return $this->render(
            'emoji/list.html.twig',
            [
                'emojis' => $emojis,
                'form' => $form,
                'criteria' => $criteria,
            ]
        );
    }
}
