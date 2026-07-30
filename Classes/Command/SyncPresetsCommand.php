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
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompt for destructive modes (strict, reset)'
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

        if (!$this->confirmDestructiveMode($io, $input, $mode, $presetOption)) {
            return Command::SUCCESS;
        }

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

            if ($result->success && !$result->changed) {
                $io->note(sprintf(
                    'Preset "%s" (uid=%s): Nothing to sync',
                    $result->presetKey !== '' ? $result->presetKey : (string)$presetOption,
                    (string)($result->presetUid ?? '-')
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
            } elseif ($result->success && !$result->changed) {
                $io->writeln(sprintf('<comment>–</comment> %s — Nothing to sync', $label));
            } elseif ($result->success && $this->hasWarning($result->notifications)) {
                $io->writeln(sprintf('<comment>!</comment> %s — %s', $label, $result->message));
            } elseif ($result->success) {
                $io->writeln(sprintf('<info>✓</info> %s — %s', $label, $result->message));
            } else {
                $io->writeln(sprintf('<error>✗</error> %s — %s', $label, $result->message));
            }
        }

        if (
            $batch['synced'] === 0
            && $batch['unchanged'] === 0
            && $batch['skipped'] === 0
            && $batch['failed'] === 0
        ) {
            $io->warning('No presets found in the database.');
            return Command::SUCCESS;
        }

        if ($batch['success']) {
            $io->success(sprintf(
                'Synced %d preset(s); nothing to sync for %d; skipped %d.',
                $batch['synced'],
                $batch['unchanged'],
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
     * Ask for confirmation before strict (overwrites DB from YAML) or reset (clears DB overrides).
     */
    private function confirmDestructiveMode(
        SymfonyStyle $io,
        InputInterface $input,
        SyncMode $mode,
        mixed $presetOption
    ): bool {
        if ($mode !== SyncMode::Strict && $mode !== SyncMode::Reset) {
            return true;
        }

        if ($input->getOption('force')) {
            return true;
        }

        $scope = ($presetOption !== null && $presetOption !== '')
            ? sprintf('preset "%s"', (string)$presetOption)
            : 'all database presets';

        if ($mode === SyncMode::Strict) {
            $io->warning(sprintf(
                'Strict mode overwrites database toolbar and feature configuration from YAML for %s. Custom DB-only changes may be lost.',
                $scope
            ));
        } else {
            $io->warning(sprintf(
                'Reset clears database toolbar overrides and stored feature rows for %s. The editor will fall back to YAML until you configure it again.',
                $scope
            ));
        }

        if ($input->isInteractive()) {
            if (!$io->confirm('Are you sure you want to proceed?', false)) {
                $io->note('Operation cancelled.');
                return false;
            }
            return true;
        }

        $io->note('Non-interactive mode: re-run with --force to proceed without confirmation.');
        return false;
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
