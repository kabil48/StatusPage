<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\IncidentUpdateInput;
use App\Enum\IncidentStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<IncidentUpdateInput> */
final class IncidentUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'class' => IncidentStatus::class,
                'label' => 'Nouveau statut',
                'choice_label' => static fn (IncidentStatus $s) => $s->label(),
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['rows' => 3, 'placeholder' => 'Ex. : La cause a été identifiée, un correctif est en cours de déploiement.'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => IncidentUpdateInput::class]);
    }
}
