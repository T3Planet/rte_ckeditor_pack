<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use T3Planet\RteCkeditorPack\Service\PresetSyncService;
use T3Planet\RteCkeditorPack\Service\SyncMode;

#[AsCommand(
    name: 'rteckeditorpack:presets:sync',
    description: 'Sync CKEditor Pack presets from YAML into the database',
)]
final class SyncPresetsCommand extends Command
{
    public function __construct(
        private readonly PresetSyncService $presetSyncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'preset',
                'p',
                InputOption::VALUE_REQUIRED,
                'Preset key or UID to sync (omit to sync all DB presets)'
            )
            ->addOption(
                'mode',
                'm',
                InputOption::VALUE_REQUIRED,
                'Sync mode: additive, ordered (position-aware), strict (YAML wins), or reset',
                SyncMode::Additive->value
            )
            ->addOption(
                'strict',
                null,
                InputOption::VALUE_NONE,
                'Shortcut for --mode=strict (YAML is authoritative)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $mode = $input->getOption('strict')
                ? SyncMode::Strict
                : SyncMode::fromInput((string)$input->getOption('mode'));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $presetOption = $input->getOption('preset');
        $io->title(sprintf('CKEditor Pack preset sync (%s)', $mode->value));

        if ($presetOption !== null && $presetOption !== '') {
            $result = $this->presetSyncService->syncPreset(
                is_numeric($presetOption) ? (int)$presetOption : (string)$presetOption,
                $mode
            );

            if ($result->skipped) {
                $io->note(sprintf(
                    'Skipped preset "%s": %s',
                    $result->presetKey !== '' ? $result->presetKey : (string)$presetOption,
                    $result->message
                ));
                return Command::SUCCESS;
            }

            if ($result->success && $this->hasWarning($result->notifications)) {
                $io->warning(sprintf(
                    'Preset "%s": %s',
                    $result->presetKey !== '' ? $result->presetKey : (string)$presetOption,
                    $result->message
                ));
                return Command::SUCCESS;
            }

            if ($result->success) {
                $io->success(sprintf(
                    'Synced preset "%s" (uid=%s): %s',
                    $result->presetKey !== '' ? $result->presetKey : (string)$presetOption,
                    (string)($result->presetUid ?? '-'),
                    $result->message
                ));
                return Command::SUCCESS;
            }

            $io->error(sprintf(
                'Failed to sync preset "%s": %s',
                (string)$presetOption,
                $result->message
            ));
            return Command::FAILURE;
        }

        $batch = $this->presetSyncService->syncAll($mode);
        foreach ($batch['results'] as $result) {
            $label = $result->presetKey !== '' ? $result->presetKey : (string)($result->presetUid ?? '?');
            if ($result->skipped) {
                $io->writeln(sprintf('<comment>–</comment> %s — %s', $label, $result->message));
            } elseif ($result->success && $this->hasWarning($result->notifications)) {
                $io->writeln(sprintf('<comment>!</comment> %s — %s', $label, $result->message));
            } elseif ($result->success) {
                $io->writeln(sprintf('<info>✓</info> %s — %s', $label, $result->message));
            } else {
                $io->writeln(sprintf('<error>✗</error> %s — %s', $label, $result->message));
            }
        }

        if ($batch['synced'] === 0 && $batch['skipped'] === 0 && $batch['failed'] === 0) {
            $io->warning('No presets found in the database.');
            return Command::SUCCESS;
        }

        if ($batch['success']) {
            $io->success(sprintf(
                'Synced %d preset(s); skipped %d. Cache flushed.',
                $batch['synced'],
                $batch['skipped']
            ));
            return Command::SUCCESS;
        }

        $io->error(sprintf(
            'Completed with errors: %d synced, %d skipped, %d failed.',
            $batch['synced'],
            $batch['skipped'],
            $batch['failed']
        ));
        return Command::FAILURE;
    }

    /**
     * @param list<array{title: string, message?: string, severity: int}> $notifications
     */
    private function hasWarning(array $notifications): bool
    {
        foreach ($notifications as $notification) {
            if (($notification['severity'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }
}
