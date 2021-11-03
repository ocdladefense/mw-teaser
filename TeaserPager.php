<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008-2010 Juliano F. Ravasi
 * http://www.mediawiki.org/wiki/Extension:Wikilog
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * @file
 * @ingroup Extensions
 * @author Juliano F. Ravasi < dev juliano info >
 */

if ( !defined( 'MEDIAWIKI' ) )
	die();

/**
 * Common wikilog pager interface.
 */
interface ITeaserPager
	extends Pager
{
	function including( $x = null );
}

/**
 * Summary pager.
 *
 * Lists wikilog articles from one or more wikilogs (selected by the provided
 * query parameters) in reverse chronological order, displaying article
 * sumaries, authors, date and number of comments. This pager also provides
 * a "read more" link when appropriate. If there are more articles than
 * some threshold, the user may navigate through "newer posts"/"older posts"
 * links.
 *
 * Formatting is controlled by a number of system messages.
 */
class TeaserPager
	extends ReverseChronologicalPager
	// implements WikilogItemPager
{
	# Override default limits.
	public $mLimitsShown = array( 5, 10, 20, 50 );

	# Local variables.
	protected $mQuery = null;			///< Wikilog item query data
	protected $mIncluding = false;		///< If pager is being included
	protected $mShowEditLink = false;	///< If edit links are shown.

	/**
	 * Constructor.
	 * @param $query Query object, containing the parameters that will select
	 *   which articles will be shown.
	 * @param $limit Override how many articles will be listed.
	 */
	function __construct( WikilogItemQuery $query, $limit = false, $including = false ) {
		# WikilogItemQuery object drives our queries.
		$this->mQuery = $query;
		$this->mIncluding = $including;

		# Parent constructor.
		parent::__construct();

		# Fix our limits, Pager's defaults are too high.
		global $wgUser, $wgWikilogNumArticles;
		$this->mDefaultLimit = $wgWikilogNumArticles;

		if ( $limit ) {
			$this->mLimit = $limit;
		} else {
			list( $this->mLimit, /* $offset */ ) =
				$this->mRequest->getLimitOffset( $wgWikilogNumArticles, '' );
		}

		# This is too expensive, limit listing.
		global $wgWikilogExpensiveLimit;
		if ( $this->mLimit > $wgWikilogExpensiveLimit )
			$this->mLimit = $wgWikilogExpensiveLimit;

		# Check parser state, setup edit links.
		global $wgOut, $wgParser, $wgTitle;
		if ( $this->mIncluding ) {
			$popt = $wgParser->getOptions();
		} else {
			$popt = $wgOut->parserOptions();

			# We will need a clean parser if not including.
			$wgParser->startExternalParse( $wgTitle, $popt, Parser::OT_HTML );
		}
		$this->mShowEditLink = $popt->getEditSection();
	}

	/**
	 * Property accessor/mutators.
	 */
	function including( $x = null ) { return wfSetVar( $this->mIncluding, $x ); }

	function getQueryInfo() {
		return $this->mQuery->getQueryInfo( $this->mDb );
	}

	function getDefaultQuery() {
		return parent::getDefaultQuery() + $this->mQuery->getDefaultQuery();
	}

	function getIndexField() {
		return 'wlp_pubdate';
	}

	function getStartBody() {
		return "<div class=\"wl-roll \">\n";
	}

	function getEndBody() {
		return "</div>\n";
	}

	function getEmptyBody() {
		return '<div class="wl-empty">' . wfMsgExt( 'wikilog-pager-empty', array( 'parsemag' ) ) . "</div>";
	}

	function getNavigationBar() {
		if ( !$this->isNavigationBarShown() ) return '';
		if ( !isset( $this->mNavigationBar ) ) {
			$navbar = new WikilogNavbar( $this, 'chrono-rev' );
			$this->mNavigationBar = $navbar->getNavigationBar( $this->mLimit );
		}
		return $this->mNavigationBar;
	}

	function formatRow( $row ) {
		global $wgWikilogExtSummaries;
		$skin = $this->getSkin();
		$header = $footer = '';

		# Retrieve article parser output and other data.
		$item = WikilogItem::newFromRow( $row );
		list( $article, $parserOutput ) = WikilogUtils::parsedArticle( $item->mTitle );
		list( $summary, $content ) = WikilogUtils::splitSummaryContent( $parserOutput );

		# Retrieve the common header and footer parameters.
		$params = $item->getMsgParams( $wgWikilogExtSummaries, $parserOutput );

		# Article title heading, with direct link article page and optional
		# edit link (if user can edit the article).
		$titleText = Sanitizer::escapeHtmlAllowEntities( $item->mName );
		if ( !$item->getIsPublished() )
			$titleText .= wfMsgForContent( 'wikilog-draft-title-mark' );
		$heading = $skin->link( $item->mTitle, $titleText, array(), array(),
			array( 'known', 'noclasses' )
		);
		if ( $this->mShowEditLink && $item->mTitle->quickUserCan( 'edit' ) ) {
			$heading = $this->doEditLink( $item->mTitle, $item->mName ) . $heading;
		}
		$heading = Xml::tags( 'h2', null, $heading );

		# Sumary entry header.
		$key = $this->mQuery->isSingleWikilog()
			? 'wikilog-summary-header-single'
			: 'wikilog-summary-header';
		$msg = wfMsgExt( $key, array( 'content', 'parsemag' ), $params );
		if ( !empty( $msg ) ) {
			$header = WikilogUtils::wrapDiv( 'wl-summary-header', $this->parse( $msg ) );
		}

		# Summary entry text.
		if ( $summary ) {
			$more = $this->parse( wfMsgForContentNoTrans( 'wikilog-summary-more', $params ) );
			$summary = WikilogUtils::wrapDiv( 'wl-summary', $summary . $more );
		} else {
			$summary = WikilogUtils::wrapDiv( 'wl-summary', $content );
		}

		# Summary entry footer.
		$key = $this->mQuery->isSingleWikilog()
			? 'wikilog-summary-footer-single'
			: 'wikilog-summary-footer';
		$msg = wfMsgExt( $key, array( 'content', 'parsemag' ), $params );
		if ( !empty( $msg ) ) {
			$footer = WikilogUtils::wrapDiv( 'wl-summary-footer', $this->parse( $msg ) );
		}

		# Assembly the entry div.
		$divclass = array( 'wl-entry' );
		if ( !$item->getIsPublished() )
			$divclass[] = 'wl-draft';
		$entry = WikilogUtils::wrapDiv(
			implode( ' ', $divclass ),
			$heading . $header . $summary . $footer
		);
		return $entry;
	}

	/**
	 * Parse a given wikitext and returns the resulting HTML fragment.
	 * Uses either $wgParser->recursiveTagParse() or $wgParser->parse()
	 * depending whether the content is being included in another
	 * article. Note that the parser state can't be reset, or it will
	 * break the parser output.
	 * @param $text Wikitext that should be parsed.
	 * @return Resulting HTML fragment.
	 */
	protected function parse( $text ) {
		global $wgTitle, $wgParser, $wgOut;
		if ( $this->mIncluding ) {
			return $wgParser->recursiveTagParse( $text );
		} else {
			$popts = $wgOut->parserOptions();
			$output = $wgParser->parse( $text, $wgTitle, $popts, true, false );
			return $output->getText();
		}
	}

	/**
	 * Returns a wikilog article edit link, much similar to a section edit
	 * link in normal articles.
	 * @param $title Title  The title of the target article.
	 * @param $tooltip string  The tooltip to be included in the link, wrapped
	 *   in the 'wikilog-edit-hint' message.
	 * @return string  HTML fragment.
	 */
	private function doEditLink( $title, $tooltip = null ) {
		$skin = $this->getSkin();
		$attribs = array();
		if ( !is_null( $tooltip ) ) {
			$attribs['title'] = wfMsg( 'wikilog-edit-hint', $tooltip );
		}
		$link = $skin->link( $title, wfMsg( 'wikilog-edit-lc' ),
			$attribs,
			array( 'action' => 'edit' ),
			array( 'noclasses', 'known' )
		);

		$result = wfMsgHtml ( 'editsection-brackets', $link );
		$result = "<span class=\"editsection\">$result</span>";

		wfRunHooks( 'DoEditSectionLink', array( $skin, $title, "", $tooltip, &$result ) );
		return $result;
	}
}