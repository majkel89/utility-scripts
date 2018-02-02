<?php
/**
 * Created by PhpStorm.
 * User: Michał Kowalik <maf.michal@gmail.com>
 * Date: 16.08.16 21:57
 */

namespace org\majkel\utility_scripts\Git;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use InvalidArgumentException;

/**
 * Class AddRefToCommitMessageCommand
 *
 * @author Michał Kowalik <maf.michal@gmail.com>
 */
class AddRefToCommitMessageCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('git:add-ref-to-commit-msg');
        $this->setDescription('Add task reference to commit message.');
        $this->addArgument(
            'commitMessageFile',
            InputArgument::OPTIONAL,
            'Path to commit message file',
            '.git/COMMIT_EDITMSG'
        );
        $this->addOption(
            'skipBranches',
            's',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'List of patterns matching branches to skip',
            ['#^(master|develop|dev)$#i']
        );

        $this->addOption(
            'branchPatterns',
            'b',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'List of patterns matching branches from witch to extract task ref. Has to contain {TASK_ID} phrase',
            ['#^(hotfix/|task/|bug/|feature/|issue/|bugfix/|story/|epic/)?{TASK_ID}#i']
        );
        $this->addOption(
            'pattern',
            'p',
            InputOption::VALUE_REQUIRED,
            'Commit message pattern (available variables {TASK_ID},{COMMIT_MESSAGE})',
            '{TASK_ID} {COMMIT_MESSAGE}'
        );
        $this->addOption(
            'taskPattern',
            't',
            InputOption::VALUE_REQUIRED,
            'Task ID pattern',
            '[A-Z]+\-\d+'
        );
        $this->setHelp('
    Installation:

        cp .git/hooks/commit-msg.sample .git/hooks/commit-msg
        echo "./utility_scripts.php git:add-ref-to-commit-msg \$1; exit \$?" > .git/hooks/commit-msg
        
    Now you can use
        
        git commit -m "message"
        
    instead of
        
        git commit -m "refs #123 message"
        
    To skip hook
        
        git commit -n -m "refs #otherTaskId message"
        ');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $commitMessageFile = $input->getArgument('commitMessageFile');
        if (!is_readable($commitMessageFile) || !is_writable($commitMessageFile)) {
            $this->error($output, "Cannot read / write to commit message file `$commitMessageFile`");
            return 1;
        }
        if ($output->isVerbose()) {
            $output->writeln("<info>Commit message file:</info> $commitMessageFile");
        }

        $currentGitBranch = trim(shell_exec("git rev-parse --abbrev-ref HEAD"));
        if (empty($currentGitBranch)) {
            $this->error($output, "Not a git repository");
            return 3;
        }
        if ($output->isVerbose()) {
            $output->writeln("<info>Current branch:</info> $currentGitBranch");
        }

        if ($this->skipByPattern($currentGitBranch, $input->getOption('skipBranches'))) {
            $output->writeln("<question>Skipping branch</question>");
            return 0;
        }

        $taskId = $this->getTaskId($currentGitBranch, $this->getBranchPatters($input));
        if (!$taskId) {
            $this->error($output, "Invalid branch name `$currentGitBranch`. To commit changes anyway run git commit with -n flag eg. `git commit -n -m 'commit message'`");
            return 4;
        }
        if ($output->isVerbose()) {
            $output->writeln("<info>Task id:</info> $taskId");
        }

        $commitMessagePattern = $input->getOption('pattern');
        if ($output->isVerbose()) {
            $output->writeln("<info>Commit message pattern:</info> $commitMessagePattern");
        }

        $currentCommitMessage = file_get_contents($commitMessageFile);
        $commitMsgPattern = str_replace(
            [$this->quote('{TASK_ID}'), $this->quote('{COMMIT_MESSAGE}')],
            [$input->getOption('taskPattern'), '.*'],
            $this->quote($commitMessagePattern)
        );

        if ($output->isVerbose()) {
            $output->writeln("<info>Checking commit message:</info> $currentCommitMessage using pattern $commitMsgPattern");
        }

        if (preg_match("#$commitMsgPattern#i", $currentCommitMessage)) {
            $output->writeln("<question>Valid commit message already provided</question>");
            return 0;
        }

        $commitMessage = str_replace(['{TASK_ID}', '{COMMIT_MESSAGE}'], [$taskId, $currentCommitMessage], $commitMessagePattern);

        file_put_contents($commitMessageFile, $commitMessage);

        $commitMessage = trim($commitMessage);

        $output->writeln("<question>Changed commit message to `$commitMessage`</question>");

        return  0;
    }

    /**
     * @param string   $value
     * @param string[] $patterns
     *
     * @return bool
     */
    private function skipByPattern($value, array $patterns)
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string   $branch
     * @param string[] $branchPatterns
     *
     * @return false|string
     */
    private function getTaskId($branch, array $branchPatterns)
    {
        foreach ($branchPatterns as $branchPattern) {
            if (preg_match($branchPattern, $branch, $matches) && isset($matches['TASK_ID'])) {
                return $matches['TASK_ID'];
            }
        }
        return false;
    }

    /**
     * @param string $data
     *
     * @return string
     */
    private function quote($data) {
        return preg_quote($data, '#');
    }

    /**
     * @param OutputInterface $output
     * @param string          $message
     */
    private function error(OutputInterface $output, $message)
    {
        $output->writeln("<error>$message</error>");
    }

    /**
     * @param InputInterface $input
     * @return mixed
     */
    private function getBranchPatters(InputInterface $input)
    {
        $branchPatterns = $input->getOption('branchPatterns');
        foreach ($branchPatterns as $i => $branchPattern) {
            if (strpos($branchPattern, '{TASK_ID}') === false) {
                throw new InvalidArgumentException("Missing {TASK_ID} in branchPatterns[{$i}]");
            }
            $branchPatterns[$i] = str_replace('{TASK_ID}', "(?<TASK_ID>{$input->getOption('taskPattern')})", $branchPattern);
        }
        return $branchPatterns;
    }
}
