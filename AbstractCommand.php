<?php

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

abstract class AbstractCommand extends ContainerAwareCommand
{
    const EXIT_CODE_OK = 0;
    const EXIT_CODE_KO = 1;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var SymfonyStyle
     */
    protected $style;

    abstract protected function doExecute(): int;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();

        $this->logger = $logger;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;
        $this->style  = new SymfonyStyle($input, $output);

        $this->title($this->getName());
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->askForMissingArgumentsRequired();
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     */
    private function askForMissingArgumentsRequired(): void
    {
        $addSection = false;
        foreach ($this->getDefinition()->getArguments() as $argument) {
            if ($argument->isRequired() === true && $this->isEmptyArgument($argument) === true) {
                if ($addSection === false) {
                    $this->section('Arguments mandatories');
                    $addSection = true;
                }

                $argumentValue = $this->style->ask("Please enter the value of {$argument->getName()}");
                if ($argument->isArray() === true) {
                    $argumentValue = explode(' ', $argumentValue);
                }

                $this->input->setArgument($argument->getName(), $argumentValue);
            }
        }
    }

    /**
     * @param InputArgument $argument
     *
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     *
     * @return bool
     */
    private function isEmptyArgument(InputArgument $argument): bool
    {
        return in_array(
            $this->getArgument($argument->getName()),
            [[], null]
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStarted = microtime(true);
        $execute     = $this->doExecute();
        $timeEnded   = round(microtime(true) - $timeStarted, 2);

        $this->writelnInfo("Batch duration: $timeEnded secondes");

        if ($execute === self::EXIT_CODE_KO) {
            return $this->ko();
        }

        return $this->ok();
    }

    /**
     * Return a service by his name.
     *
     * @param string $serviceName
     *
     * @throws \LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     *
     * @return object
     */
    protected function get($serviceName)
    {
        return $this->getContainer()->get($serviceName);
    }

    protected function writelnInfo(string $text): void
    {
        $this->writeln("<info>$text</info>");
    }

    protected function writelnComment(string $text): void
    {
        $this->writeln("<comment>$text</comment>");
    }

    protected function writelnQuestion(string $text): void
    {
        $this->writeln("<question>$text</question>");
    }

    protected function writelnError(string $text): void
    {
        $this->logger->error($text);

        $this->writeln("<error>$text</error>");
    }

    protected function writeln(string $text): void
    {
        //don't print if we aren't in verbose mod
        if ($this->isVerbose() === false) {
            return;
        }

        $this->output->writeln("<fg=cyan>[" . date('Y-m-d H:i:s') . "]</> $text");
    }

    private function isVerbose(): bool
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }

    private function ok(): int
    {
        $this->success("Batch {$this->getName()} ended ok");

        return self::EXIT_CODE_OK;
    }

    private function ko(): int
    {
        $this->writelnError("Batch {$this->getName()} ended ko");

        return self::EXIT_CODE_KO;
    }

    /**
     * Alias of $input->getArgument()
     *
     * @param string $name
     *
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     *
     * @return string|string[]|null
     */
    protected function getArgument(string $name)
    {
        return $this->input->getArgument($name);
    }

    /**
     * Alias of $input->getOption()
     *
     * @param string $name
     *
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     *
     * @return bool|string|string[]|null
     */
    protected function getOption(string $name)
    {
        return $this->input->getOption($name);
    }

    /**
     * Alias of $input->hasArgument()
     *
     * @param string|int $name The InputArgument name or position
     *
     * @return bool true if the InputArgument object exists, false otherwise
     */
    protected function hasArgument($name)
    {
        return $this->input->hasArgument($name);
    }

    /**
     * Alias of $this->style->section()
     */
    public function section(string $message)
    {
        if ($this->isVerbose() === true) {
            $this->newLine();
            $this->style->section($message);
        }
    }

    /**
     * Alias of $this->style->newLine()
     */
    public function newLine(int $count = 1)
    {
        if ($this->isVerbose() === true) {
            $this->style->newLine($count);
        }
    }

    /**
     * Alias of $this->style->title()
     */
    public function title(string $message)
    {
        if ($this->isVerbose() === true) {
            $this->style->title($message);
        }
    }

    /**
     * Alias of $this->style->success()
     */
    public function success(string $message)
    {
        if ($this->isVerbose() === true) {
            $this->newLine();
            $this->style->success($message);
        }
    }

    /**
     * Alias of $this->style->progressStart()
     */
    public function progressStart(int $max = 0)
    {
        if ($this->isVerbose() === true) {
            $this->style->progressStart($max);
        }
    }

    /**
     * Alias of $this->style->progressAdvance()
     *
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     */
    public function progressAdvance(int $step = 1)
    {
        if ($this->isVerbose() === true) {
            $this->style->progressAdvance($step);
        }
    }

    /**
     * Alias of $this->style->progressFinish()
     *
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     */
    public function progressFinish()
    {
        if ($this->isVerbose() === true) {
            $this->style->progressFinish();
            $this->newLine();
        }
    }

    /**
     * @param string $parameter
     *
     * @throws \LogicException
     *
     * @return mixed
     */
    public function getParameter(string $parameter)
    {
        return $this->getContainer()->getParameter($parameter);
    }
}
