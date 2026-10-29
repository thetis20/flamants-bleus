<?php

namespace App\Command;

use App\Model\Event;
use App\Model\Format;
use App\Model\Venue;
use App\Repository\EventRepositoryInterface;
use App\Repository\FormatRepositoryInterface;
use App\Repository\VenueRepositoryInterface;
use App\Storage\LogoStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Fills a local Firestore emulator with the formats, venues and dates of the autumn 2026 flyer.
 */
#[AsCommand('app:load-demo', 'Charge les formats, lieux et dates du flyer (dev uniquement)')]
final class LoadDemoCommand
{
    private const FORMATS = [
        'time' => ['Time : 30 impros en 1h', 'time-30-impros', 'time.png', "Un chrono, une heure, trente improvisations.\nLe public choisit, les comédiens enchaînent sans filet."],
        'cabaret' => ['Cabaret d\'impro', 'cabaret-impro', 'cabaret.png', "Un maître de cérémonie, des thèmes tirés au sort et vos idées.\nLe format convivial par excellence."],
        'doublage' => ['Doublage Improvisé', 'doublage-improvise', 'doublage.png', "Des extraits de films projetés sans le son, doublés en direct par la troupe."],
        'time-spin-off' => ['Time Spin-Off : 31 impros en 1h30', 'time-spin-off', 'time-spin-off.png', null],
        'catch' => ['Catch d\'impro', 'catch-impro', 'catch.png', null],
        'battle' => ['Battle Royale', 'battle-royale', 'battle.png', null],
    ];

    private const VENUES = [
        'folies' => ['Les Folies Angevines', 'folies-angevines', 'folies-angevines.png'],
        'blue-monkeys' => ['Blue Monkeys', 'blue-monkeys', 'blue-monkeys.jpg'],
        'digital-village' => ['Digital Village', 'digital-village', 'digital-village.png'],
        'cafe-du-village' => ['Le Café du Village', 'cafe-du-village', 'cafe-du-village.png'],
        'stella' => ['Stella Ciné Baugé', 'stella-cine-bauge', 'cinema-bauge.png'],
        'lions' => ['Des Lions dans ton salon', 'des-lions-dans-ton-salon', 'lions-dans-ton-salon.png'],
        'verdoyantes' => ['Les Verdoyantes', 'les-verdoyantes', 'les-verdoyantes.png'],
        'tiffauges' => ['École Notre-Dame Tiffauges', 'ecole-notre-dame-tiffauges', 'tiffauges.jpg'],
    ];

    private const EVENTS = [
        ['Time : 30 impros en 1h', '2026-10-08 19:00', 'time', 'folies', null],
        ['Cabaret d\'impro', '2026-10-23 20:00', 'cabaret', 'blue-monkeys', null],
        ['Doublage Improvisé', '2026-11-06 20:00', 'doublage', 'digital-village', 'https://www.helloasso.com/associations/compagnie-les-flamants-bleus/evenements/doublage-improvise-les-flamants-bleus-1'],
        ['Cabaret d\'impro', '2026-11-20 20:00', 'cabaret', 'blue-monkeys', null],
    ];

    public function __construct(
        private FormatRepositoryInterface $formats,
        private EventRepositoryInterface $events,
        private VenueRepositoryInterface $venues,
        private LogoStorage $logos,
        #[Autowire('%kernel.project_dir%/fixtures/logos')]
        private string $logoDir,
        #[Autowire('%kernel.project_dir%/fixtures/lieux')]
        private string $venueLogoDir,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if ('dev' !== $this->environment) {
            $io->error('Réservé à l\'environnement dev.');

            return Command::FAILURE;
        }

        $ids = [];
        foreach (self::FORMATS as $key => [$title, $slug, $logo, $description]) {
            $format = $this->formats->findBySlug($slug) ?? new Format();
            $format->title = $title;
            $format->slug = $slug;
            $format->description = $description;
            $format->logoPath ??= $this->logos->store(new File($this->logoDir.'/'.$logo), $slug);
            $this->formats->save($format);
            $ids[$key] = $format->id;
        }

        $venueIds = [];
        foreach (self::VENUES as $key => [$name, $slug, $logo]) {
            $venue = $this->venues->findBySlug($slug) ?? new Venue();
            $venue->name = $name;
            $venue->slug = $slug;
            $venue->onHome = true;
            $venue->logoPath ??= $this->logos->store(new File($this->venueLogoDir.'/'.$logo), 'lieu-'.$slug);
            $this->venues->save($venue);
            $venueIds[$key] = $venue->id;
        }

        foreach (self::EVENTS as $i => [$title, $startsAt, $formatKey, $venueKey, $bookingUrl]) {
            $event = new Event('demo-'.$i);
            $event->title = $title;
            $event->startsAt = new \DateTimeImmutable($startsAt);
            $event->formatId = $ids[$formatKey];
            $event->venueId = $venueIds[$venueKey];
            $event->bookingUrl = $bookingUrl;
            $this->events->save($event);
        }

        $io->success(\sprintf('%d formats, %d lieux et %d dates chargés.', \count(self::FORMATS), \count(self::VENUES), \count(self::EVENTS)));

        return Command::SUCCESS;
    }
}
