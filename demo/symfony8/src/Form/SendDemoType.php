<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`) themed on top of UiKit's semantic classes.
 *
 * @extends AbstractType<array{to: string, body: string}>
 */
#[FormKitConfig('demo')]
final class SendDemoType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'to', TelType::class, [
            'label'       => 'Recipient (E.164)',
            'constraints' => [new NotBlank()],
        ]);
        $this->addWithDefaults($builder, 'body', TextareaType::class, [
            'label'       => 'Message',
            'constraints' => [new NotBlank()],
        ]);
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label' => 'Send text (mock)',
            'attr'  => ['class' => 'nowo-ui-btn nowo-ui-btn--primary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'POST',
        ]);
    }
}
