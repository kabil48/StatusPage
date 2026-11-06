<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\SluggerInterface;

/** @extends AbstractType<Project> */
final class ProjectType extends AbstractType
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du projet',
                'attr' => ['placeholder' => 'Ex. : Mon SaaS'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Adresse de la page de statut',
                'required' => false,
                'help' => 'Votre page sera accessible sur /status/<adresse>. Laissez vide pour la générer depuis le nom.',
                'empty_data' => '',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('isPublic', CheckboxType::class, [
                'label' => 'Page de statut publique',
                'required' => false,
            ]);

        // Slug généré automatiquement à partir du nom s'il est laissé vide.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            /** @var array<string, mixed> $data */
            $data = $event->getData() ?? [];
            if ('' === trim((string) ($data['slug'] ?? '')) && '' !== trim((string) ($data['name'] ?? ''))) {
                $data['slug'] = $this->slugger->slug((string) $data['name'])->lower()->toString();
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
