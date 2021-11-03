<?php

if ( !defined( 'MEDIAWIKI' ) )
	die();

/**
 * General extension information.
 */
$wgExtensionCredits['specialpage'][] = array(
	'path'           				=> __FILE__,
	'name'           				=> 'Teaser',
	'version'        				=> '0.0.0.1',
	'author'         				=> 'José Bernal',
	// 'descriptionmsg' 		=> 'wikilogocdla-desc',
	// 'url'            		=> 'http://www.mediawiki.org/wiki/Extension:WikilogOcdla',
);

// $wgExtensionMessagesFiles['WikilogOcdla'] = $dir . 'WikilogOcdla.i18n.php';

$dir = dirname( __FILE__ );


$wgHooks['ParserFirstCallInit'][] = 'Teaser::onParserSetup';

$wgAutoloadClasses += array(
	// General
	// 'WikilogHooks'              => $dir . 'WikilogHooks.php',
	// 'WikilogLinksUpdate'        => $dir . 'WikilogLinksUpdate.php',
	// 'WikilogUtils'              => $dir . 'WikilogUtils.php',
	// 'WikilogNavbar'             => $dir . 'WikilogUtils.php',
	'SpecialTeaser'            => $dir . '/SpecialTeaser.php',

	// Objects
	// 'WikilogItem'               => $dir . 'WikilogItem.php',
	// 'WikilogComment'            => $dir . 'WikilogComment.php',
	// 'WikilogCommentFormatter'   => $dir . 'WikilogComment.php',

	// WikilogParser.php
	// 'WikilogParser'             => $dir . 'WikilogParser.php',
	// 'WikilogParserOutput'       => $dir . 'WikilogParser.php',

	// WikilogItemPager.php
	// 'WikilogItemPager'          => $dir . 'WikilogItemPager.php',
	'TeaserPager'       => $dir . '/TeaserPager.php',
	// 'WikilogTemplatePager'      => $dir . 'WikilogItemPager.php',
	// 'WikilogArchivesPager'      => $dir . 'WikilogItemPager.php',

	// WikilogCommentPager.php
	// 'WikilogCommentPager'       => $dir . 'WikilogCommentPager.php',
	// 'WikilogCommentListPager'   => $dir . 'WikilogCommentPager.php',
	// 'WikilogCommentThreadPager' => $dir . 'WikilogCommentPager.php',

	// WikilogFeed.php
	// 'WikilogFeed'               => $dir . 'WikilogFeed.php',
	// 'WikilogItemFeed'           => $dir . 'WikilogFeed.php',
	// 'WikilogCommentFeed'        => $dir . 'WikilogFeed.php',

	// WikilogQuery.php
	// 'WikilogQuery'              => $dir . 'WikilogQuery.php',
	// 'WikilogItemQuery'          => $dir . 'WikilogQuery.php',
	// 'WikilogCommentQuery'       => $dir . 'WikilogQuery.php',

	// Namespace pages
	// 'WikilogMainPage'           => $dir . 'WikilogMainPage.php',
	// 'WikilogItemPage'           => $dir . 'WikilogItemPage.php',
	// 'WikilogWikiItemPage'       => $dir . 'WikilogItemPage.php',
	// 'WikilogCommentsPage'       => $dir . 'WikilogCommentsPage.php',

	// Captcha adapter
	// 'WlCaptcha'                 => $dir . 'WlCaptchaAdapter.php',
	// 'WlCaptchaAdapter'          => $dir . 'WlCaptchaAdapter.php',
);

/**
 * Special pages.
 */
$wgSpecialPages['Teaser'] = 'SpecialTeaser';
#@jbernal $wgSpecialPageGroups['Teaser'] = 'changes';

/**
 * Hooks.
 */
# $wgExtensionFunctions[] = array( 'Wikilog', 'ExtensionInit' );


class Teaser {

	const DRAWER_TEMPLATE = 'new-drawer.html';

	public static function onParserSetup( Parser $parser ) {
		// When the parser sees the <sample> tag, it executes renderTagSample (see below)
		$parser->setHook( 'teaser', 'Teaser::renderTagTeaser' );
		return true;
	}

	// Render <teaser>
	public static function renderTagTeaser( $input, array $args, Parser $parser, PPFrame $frame ) {
		// Nothing exciting here, just escape the user-provided input and throw it back out again (as example)
		// return htmlspecialchars( $input );
		return "<div style='border:1px solid #666; padding:8px;'><span style='font-weight:bold;'>Article Summary<br /></span>".htmlspecialchars($input)."</div>";
	}



	public static function SetupBooksOnlineOcdla(){
		global $wgHooks, $wgResourceModules, $wgOcdlaShowBooksOnlineDrawer;
		
		// $wgHooks['SpecialSearchCreateLink'][] = 'SetupBooksOnlineOcdla::onSpecialSearchCreateLink';
		$wgHooks['BeforePageDisplay'][] = 'BooksOnlineOcdla::onBeforePageDisplay';
		

		$wgResourceModules['ext.booksOnline.webapp.js'] = array(
			'scripts' => array(
				'js/search.controller.js'
			),
			'dependencies' => array(
				// In this example, awesome.js needs the jQuery UI dialog stuff
				'clickpdx.framework.js',
			),
			'position' => 'bottom',
			'remoteBasePath' => '/extensions/BooksOnlineOcdla',
			'localBasePath' => 'extensions/BooksOnlineOcdla'
		);
		
		$wgResourceModules['ext.booksOnline.drawer'] = array(
			'styles' => array(
				'css/drawer.css',
				'css/books-online.css',
				'css/accordion.css'
			),
			'scripts' => array('js/books-online-view.js','js/books-online-loader.js'),
			'dependencies' => array('clickpdx.framework.js'),
			'position' => 'top',
			'remoteBasePath' => '/extensions/BooksOnlineOcdla',
			'localBasePath' => 'extensions/BooksOnlineOcdla'
		);
	}
	
	
	public static function onBeforePageDisplay(OutputPage &$out, Skin &$skin ) {
		global $wgOcdlaShowBooksOnlineDrawer, $wgOcdlaShowBooksOnlineNs;
		
		$checkNamespace = isset($wgOcdlaShowBooksOnlineNs) && $wgOcdlaShowBooksOnlineNs != NS_ALL;
		
		$skin->customElements = array();
		
		$title = $out->getTitle();
		$ns = $title->getNamespace();
		

		//if(in_array(strtolower($out->getPageTitle()),array('search results','search'))) {
		if($wgOcdlaShowBooksOnlineDrawer)
		{
			if($checkNamespace && !in_array($ns,$wgOcdlaShowBooksOnlineNs,true))
			{
				return true;
			}

			$out->addModules('ext.booksOnline.drawer');
			$skin->customElements = array(
				'drawer' => file_get_contents(__DIR__.'/templates/'.self::DRAWER_TEMPLATE)
			);
		}
		
		return true;
	}

}