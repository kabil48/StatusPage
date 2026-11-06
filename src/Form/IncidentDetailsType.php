<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Incident;
use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Enum\IncidentImpact;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Titre, impact et services affectés d'un incident.
 * Seuls les services du projet sont proposés (RG-INC-01).
 *
 * @extends AbstractType<Incident>
 */
final class IncidentDetailsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Project $project */
        $project = $options['project'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex. : Lenteurs sur l\'API de paiement'],
            ])
            ->add('impact', EnumType::class, [
                'class' => IncidentImpact::class,
                'label' => 'Impact',
                'choice_label' => static fn (IncidentImpact $i) => $i->label(),
            ])
            ->add('affectedServices', EntityType::class, [
                'class' => MonitoredService::class,
                'label' => 'Services affectés',
                'choices' => $project->getServices()->toArray(),
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Incident::class]);
        $resolver->setRequired('project');
        $resolver->setAllowedTypes('project', Project::class);
    }
}
