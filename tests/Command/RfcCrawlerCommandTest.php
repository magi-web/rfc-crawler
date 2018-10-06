<?php
/**
 * Created by PhpStorm.
 * User: ptiperuv
 * Date: 13/09/2018
 * Time: 20:45
 */

namespace App\Tests\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


class RfcCrawlerCommandTest  extends KernelTestCase {
	public function testExecute()
	{
		$kernel = static::createKernel();
		$kernel->boot();

		$application = new Application($kernel);

		$command = $application->find('app:crawl-rfc');
		$commandTester = new CommandTester($command);
		$commandTester->execute(array(
			'command'  => $command->getName(),
		));

		$output = $commandTester->getDisplay();
		//$this->assertContains('Username: Wouter', $output);

		// ...
	}
}