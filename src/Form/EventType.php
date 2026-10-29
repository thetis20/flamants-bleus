<?php

namespace App\Form;

use App\Model\Event;
use App\Repository\FormatRepositoryInterface;
use App\Repository\VenueRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EventType extends AbstractType
{
    public function __construct(
        private FormatRepositoryInterface $formats,
        private VenueRepositoryInterface $venues,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($this->formats->all() as $format) {
            $choices[$format->title] = $format->id;
        }
        $venueChoices = [];
        foreach ($this->venues->all() as $venue) {
            $venueChoices[$venue->name] = $venue->id;
        }

        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('startsAt', DateTimeType::class, [
                'label' => 'Date et heure',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'model_timezone' => 'Europe/Paris',
                'view_timezone' => 'Europe/Paris',
            ])
            ->add('formatId', ChoiceType::class, [
                'label' => 'Format',
                'choices' => $choices,
                'placeholder' => '— Choisir —',
            ])
            ->add('venueId', ChoiceType::class, [
                'label' => 'Lieu',
                'choices' => $venueChoices,
                'placeholder' => '— Choisir —',
            ])
            ->add('price', TextType::class, [
                'label' => 'Prix',
                'required' => false,
                'help' => 'Texte libre, par exemple « 8 € » ou « Prix libre ».',
            ])
            ->add('bookingUrl', UrlType::class, [
                'label' => 'Lien de réservation',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'Lien HelloAsso. Laisser vide s\'il n\'est pas encore créé : le site affichera « Réservations bientôt ouvertes ».',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Event::class]);
    }
}
