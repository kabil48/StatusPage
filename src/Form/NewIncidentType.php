<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\NewIncidentInput;
use App\Entity\Project;
use App\Enum\IncidentStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<NewIncidentInput> */
final class NewIncidentType extends AbstractType
{
    public function getParent(): string
    {
        return IncidentDetailsType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'class' => IncidentStatus::class,
                'label' => 'Statut initial',
                'choices' => array_filter(IncidentStatus::cases(), static fn (IncidentStatus $s) => $s->isOpen()),
                'choice_label' => static fn (IncidentStatus $s) => $s->label(),
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Premier message',
                'help' => 'Visible sur la page publique. Décrivez ce que constatent vos utilisateurs.',
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => NewIncidentInput::class]);
        $resolver->setRequired('project');
        $resolver->setAllowedTypes('project', Project::class);
    }
}
