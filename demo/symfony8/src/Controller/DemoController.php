<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\SendDemoType;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemoController extends AbstractController
{
    public function __construct(
        private readonly WhatsappClientInterface $whatsapp,
    ) {
    }

    #[Route('/', name: 'demo_home', methods: ['GET', 'POST'])]
    public function home(Request $request): Response
    {
        $form = $this->createForm(SendDemoType::class, [
            'to'   => '34611111111',
            'body' => 'Hello from WhatsappBusinessBundle',
        ]);
        $form->handleRequest($request);

        $messageId = null;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{to: string, body: string} $data */
            $data      = $form->getData();
            $messageId = $this->whatsapp->sendText($data['to'], $data['body'])->messageId;
        }

        return $this->render('demo/home.html.twig', [
            'version_badge'   => 'Symfony 8.1',
            'form'            => $form,
            'message_id'      => $messageId,
            'timeout'         => $this->whatsapp->profile()->timeout,
            'connect_timeout' => $this->whatsapp->profile()->connectTimeout,
            'transport'       => $this->whatsapp->profile()->transport->value,
        ]);
    }
}
