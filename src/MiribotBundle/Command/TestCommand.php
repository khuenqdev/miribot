<?php
/**
 * Created by PhpStorm.
 * User: Khue Quang Nguyen
 * Date: 20-Aug-17
 * Time: 11:59
 */

namespace MiribotBundle\Command;


use MiribotBundle\Model\Miribot;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Kernel;

class TestCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this->setName('miribot:test')
            ->setDescription('Test a function of Miribot.')
            ->setHelp('Nope!!!')
            ->addArgument('input', InputArgument::REQUIRED, 'User input to the bot');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Miribot $bot */
        //$bot = $this->getContainer()->get('miribot');

        $w = '\0Khue \1is \2good';
        $p = "<star index=\"1\" /> <star index='2'/> <star/>";

        $matches = array();
        preg_match_all("/<star[^>]*>/", $p, $matches);
        dump($matches);
    }
}