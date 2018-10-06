<?php
/**
 * Created by PhpStorm.
 * User: ptiperuv
 * Date: 13/09/2018
 * Time: 20:37
 */

namespace App\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class RfcCrawlerCommand extends Command {
    protected function configure() {
        $this
            // the name of the command (the part after "bin/console")
            ->setName( 'app:crawl-rfc' )
            // the short description shown while running "php bin/console list"
            ->setDescription( 'Crawls php rfc website.' )
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp( 'This command allows you to extract data straight from https://wiki.php.net/rfc' );
    }

    protected function execute( InputInterface $input, OutputInterface $output ) {
        $start = time();
        // outputs multiple lines to the console (adding "\n" at the end of each line)
        $output->writeln( [
            'RFC\'s Crawler',
            '==============',
            '',
        ] );

        $rfcPage = file_get_contents("https://wiki.php.net/rfc/deprecate-and-remove-ext-wddx");
        $rfcPage = file_get_contents("https://wiki.php.net/rfc/typed_properties_v2");
        $result = $this->extractInfosFromPage($rfcPage, "https://wiki.php.net/rfc/deprecate-and-remove-ext-wddx");
        var_dump($result);
        return 0;


        $rfcs = [];
        ProgressBar::setFormatDefinition( 'custom', '  %current%/%max% [%bar%] %percent:3s%% -- %message%' );

        $progressBar = new ProgressBar( $output, 4 );
        $progressBar->setFormat( 'custom' );

        $progressBar->setMessage( "Récupération des informations liées aux rfc à l'état \"en cours de discussion\"" );
        $progressBar->start();

        $page    = file_get_contents( 'https://wiki.php.net/rfc' );
        $crawler = new Crawler( $page );
        $crawler = $crawler->filter( '#under_discussion + .level2' );
        $links   = $crawler->filter( 'a' );
        // $len     = $links->count();

        $progressBar->setMessage( "Initialisation des crawlers" );
        $progressBar->advance();

        $mh = curl_multi_init();
        $ch = [];
        foreach ( $links as $link ) {
            $url        = 'https://wiki.php.net' . $link->getAttribute( 'href' );
            $ch[ $url ] = curl_init(); // init curl, and then setup your options
            curl_setopt( $ch[ $url ], CURLOPT_URL, $url );
            curl_setopt( $ch[ $url ], CURLOPT_RETURNTRANSFER, 1 ); // returns the result - very important
            curl_setopt( $ch[ $url ], CURLOPT_HEADER, 0 ); // no headers in the output

            curl_multi_add_handle( $mh, $ch[ $url ] );
        }

        $progressBar->setMessage( "Lancement des crawlers" );
        $progressBar->advance();

        //execute the handles
        do {
            $mrc = curl_multi_exec( $mh, $active );
        } while ( $mrc == CURLM_CALL_MULTI_PERFORM );
        do {
            curl_multi_exec( $mh, $running );
            curl_multi_select( $mh );

        } while ( $running > 0 );

        $progressBar->setMessage( "Parse des contenus" );
        $progressBar->advance();
        foreach ( $ch as $url => $handle ) {
            $rfcPage = curl_multi_getcontent( $handle ); // get the content

            $rfcs [] = $this->extractInfosFromPage($rfcPage, $url);

            curl_multi_remove_handle( $mh, $handle );
        }

        // Finalisation
        curl_multi_close( $mh );

        usort( $rfcs, function ( $a, $b ) {
            return strcmp( $a[2], $b[2] );
        } );

        $progressBar->finish();

        $output->writeln( '' );

        $table = new Table( $output );
        $table->setHeaders( [ 'Lien', 'Auteur(s)', 'Date Création', 'Date Modification' ] );
        $table->setRows( $rfcs );
        $table->render();

        $output->writeln("Temps total : " . (time() - $start) . "s");
    }

    private function extractInfosFromPage($rfcPage, $url) {
        $rfcCrawler = new Crawler( $rfcPage );
        $info       = $rfcCrawler->filter( '.docInfo' );

        $updateDate = null;
        if ( $info->count() !== 0 ) {
            preg_match( '/: (\d{4}\/\d{2}\/\d{2} \d{2}:\d{2})/', $info->text(), $dateStr );
            $date   = new \DateTime( $dateStr[1] );
            $updateDate = $date->format( 'Y-m-d H:i' );
        }

        $liInfos = $rfcCrawler->filter('.sectionedit1')->siblings()->filter('.level1')->children()->children();
        $creationDate = null;
        $authors = null;
        foreach ($liInfos as $info) {
            $value = $info->nodeValue;
            if(preg_match('/Date: (\d{4}-\d{2}-\d{2})/', $value, $dateStr)) {
                $date   = new \DateTime( $dateStr[1] );
                $creationDate = $date->format( 'Y-m-d' );
            } elseif (strpos($value, 'Author:') !== false) {
                var_dump($value);
                $authors = $value;
            }

        }
        /*exit;
        var_dump( $rfcCrawler->filter('.sectionedit1')->siblings()->filter('.level1')->children()->children());exit;
        $info = $rfcCrawler->filter('.sectionedit1')->siblings()->filter('.level1')->children()->children()->eq(1)->children();
        $creationDate = null;
        if ( $info->count() !== 0 ) {
            preg_match( '/Date: (\d{4}-\d{2}-\d{2})/', $info->text(), $dateStr );
            $date   = new \DateTime( $dateStr[1] );
            $creationDate = $date->format( 'Y-m-d' );
        }

        $info = $rfcCrawler->filter('.sectionedit1')->siblings()->filter('.level1')->children()->children()->eq(2)->children();
        $authors = null;
        if ( $info->count() !== 0 ) {
            preg_match( '/Author: (\d{4}-\d{2}-\d{2})/', $info->text(), $authStr );
            $authors = $authStr;
        }
        */

        return [$url, $authors, $creationDate, $updateDate];
    }
}
