<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Form\EmojiPageViewType;
use App\PageView\EmojiPageView;
use App\Repository\EmojiRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminEmojiController extends AbstractController
{
    public function __construct(private readonly EmojiRepository $repository)
    {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(string $category = null, string $domain = null, Request $request): Response
    {
        $criteria = new EmojiPageView(
            $this->getPageNb($request),
            $category,
            $category ? EmojiPageView::DOMAIN_LOCAL : $domain,
        );

        $form = $this->createForm(EmojiPageViewType::class, $criteria, [
            'categories' => $this->repository->getCategories(),
            'domains' => $this->repository->getDomains(),
        ]);

        $form->handleRequest($request);

        $emojis = $this->repository->findPaginated($criteria);

        return $this->render(
            'admin/emojis.html.twig',
            [
                'emojis' => $emojis,
                'form' => $form,
                'criteria' => $criteria,
            ]
        );
    }
}
