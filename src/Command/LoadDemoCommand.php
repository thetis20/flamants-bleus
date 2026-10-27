<?php

namespace App\Command;

use App\Model\Event;
use App\Model\Format;
use App\Repository\EventRepositoryInterface;
use App\Repository\FormatRepositoryInterface;
use App\Storage\LogoStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Fills a local Firestore emulator with the formats and dates of the autumn 2026 flyer.
 */
#[AsCommand('app:load-demo', 'Charge les formats et dates du flyer (dev uniquement)')]
final class LoadDemoCommand
{
    private const FORMATS = [
        'time' => ['Time : 30 impros en 1h', 'time-30-impros', 'time.png', "Un chrono, une heure, trente improvisations.\nLe public choisit, les comédiens enchaînent sans filet."],
        'cabaret' => ['Cabaret d\'impro', 'cabaret-impro', 'cabaret.png', "Un maître de cérémonie, des thèmes tirés au sort et vos idées.\nLe format convivial par excellence."],
        'doublage' => ['Doublage Improvisé', 'doublage-improvise', 'doublage.png', "Des extraits de films projetés sans le son, doublés en direct par la troupe."],
    ];

    private const EVENTS = [
        ['Time : 30 impros en 1h', '2026-10-08 19:00', 'time', 'Les Folies Angevines', null],
        ['Cabaret d\'impro', '2026-10-23 20:00', 'cabaret', 'Blue Monkey', null],
        ['Doublage Improvisé', '2026-11-06 20:00', 'doublage', 'Digital Village', 'https://www.helloasso.com/associations/compagnie-les-flamants-bleus/evenements/doublage-improvise-les-flamants-bleus-1'],
        ['Cabaret d\'impro', '2026-11-20 20:00', 'cabaret', 'Blue Monkey', null],
    ];

    public function __construct(
        private FormatRepositoryInterface $formats,
        private EventRepositoryInterface $events,
        private LogoStorage $logos,
        #[Autowire('%kernel.project_dir%/fixtures/logos')]
        private string $logoDir,
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

        foreach (self::EVENTS as $i => [$title, $startsAt, $formatKey, $venue, $bookingUrl]) {
            $event = new Event('demo-'.$i);
            $event->title = $title;
            $event->startsAt = new \DateTimeImmutable($startsAt);
            $event->formatId = $ids[$formatKey];
            $event->venue = $venue;
            $event->bookingUrl = $bookingUrl;
            $this->events->save($event);
        }

        $io->success(\sprintf('%d formats et %d dates chargés.', \count(self::FORMATS), \count(self::EVENTS)));

        return Command::SUCCESS;
    }
}
