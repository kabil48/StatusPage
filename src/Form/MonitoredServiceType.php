<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MonitoredService;
use App\Enum\CheckInterval;
use App\Enum\HttpMethod;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<MonitoredService> */
final class MonitoredServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du service',
                'attr' => ['placeholder' => 'Ex. : API Production'],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL à surveiller',
                'default_protocol' => 'https',
                'attr' => ['placeholder' => 'https://api.example.com/health'],
            ])
            ->add('httpMethod', EnumType::class, [
                'class' => HttpMethod::class,
                'label' => 'Méthode HTTP',
                'choice_label' => static fn (HttpMethod $m) => $m->value,
            ])
            ->add('expectedStatusCode', IntegerType::class, [
                'label' => 'Code HTTP attendu',
            ])
            ->add('checkInterval', EnumType::class, [
                'class' => CheckInterval::class,
                'label' => 'Fréquence de vérification',
                'choice_label' => static fn (CheckInterval $i) => $i->label(),
            ])
            ->add('timeoutSeconds', IntegerType::class, [
                'label' => 'Timeout (secondes)',
                'attr' => ['min' => 1, 'max' => 30],
            ])
            ->add('degradedThresholdMs', IntegerType::class, [
                'label' => 'Seuil « dégradé » (ms)',
                'help' => 'Au-delà de ce temps de réponse, le service est affiché comme dégradé.',
            ])
            ->add('failureThreshold', IntegerType::class, [
                'label' => 'Échecs consécutifs avant alerte',
                'help' => 'Évite les fausses alertes dues à une erreur ponctuelle.',
                'attr' => ['min' => 1, 'max' => 10],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
            ])
            ->add('isPublic', CheckboxType::class, [
                'label' => 'Visible sur la page de statut publique',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MonitoredService::class,
        ]);
    }
}
