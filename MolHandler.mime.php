<?php
/**
 * Handler for Chemical table files
 *
 * Hooks for MIME detection.
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
 * @file
 * @ingroup Media
 */

class MolHandlerMime {

	/**
	 * Determines whether $extension is one that is used for chemical table files.
	 *
	 * @param string $extension File extension
	 * @return bool
	 */
	private static function isChemFileExtension( $extension ) {
		static $types = [
			'mol', 'sdf', 'rxn', 'rd', 'rg',
		];
		return in_array( strtolower( $extension ), $types );
	}

	/**
	 * Looks whether the MIME type could be improved from file extension.
	 *
	 * @param MimeAnalyzer $mimeMagic
	 * @param string $ext File extension
	 * @param string $mime Previously detected MIME
	 * @return string Improved MIME
	 */
	public static function improveFromExtension( $mimeMagic, $ext, $mime ) {
		if ( ( $mime === 'text/plain' ) && self::isChemFileExtension( $ext ) ) {
			$mime = $mimeMagic->guessTypesForExtension( $ext );
		}
		return $mime;
	}

	/**
	 * Hook: MimeMagicImproveFromExtension.
	 *
	 * @param MimeAnalyzer $mimeMagic
	 * @param string $ext File extension
	 * @param string &$mime In: Previously detected MIME; Out: Improved MIME
	 * @return bool Always true
	 */
	public static function onMimeMagicImproveFromExtension( $mimeMagic, $ext, &$mime ) {
		$mime = self::improveFromExtension( $mimeMagic, $ext, $mime );
		return true;
	}

	/**
	 * Guess chemical MIME types from file content.
	 *
	 * @param string &$head 1024 bytes of the head
	 * @param string &$tail 1024 bytes of the tail
	 * @param string $file
	 * @return bool|string Mime type
	 */
	public static function doGuessChemicalMime( &$head, &$tail, $file ) {
		# Note that a lot of chemical table files contain embedded molfiles.
		# Therefore, always check for these container files before checking for molfiles!
		static $headers = [
			'$RXN'                              => 'chemical/x-mdl-rxnfile',
			'$RDFILE '                          => 'chemical/x-mdl-rdfile',
			'$MDL'                              => 'chemical/x-mdl-rgfile',
		];
		static $tailsRegExps = [
			'/\n\s*\$\$\$\$\s*$/'               => 'chemical/x-mdl-sdfile',
			# MDL-Molfile with all kind of line endings
			'/\n\s*M  END\s*$/'                 => 'chemical/x-mdl-molfile',
		];
		static $headersRegExps = [
			# MDL-Molfile counts line
			# #atoms #bond_numbers #atom_lists [obsolete] [999|#propery_lines] V<version>
			'/\n(\s*\d{1,3}\s+){3}[^\n]*(?:\d+\s+){1,12}V\d{4,5}\n/'
				=> 'chemical/x-mdl-molfile',
		];

		# Compare headers
		foreach ( $headers as $magic => $candidate ) {
			if ( strncmp( $head, $magic, strlen( $magic ) ) === 0 ) {
				wfDebug( __METHOD__ .
					": magic header in $file recognized as $candidate\n" );
				return $candidate;
			}
		}

		# Match tails
		foreach ( $tailsRegExps as $regExp => $candidate ) {
			if ( preg_match( $regExp, $tail ) ) {
				wfDebug( __METHOD__ .
					": $file tail recognized by regexp as $candidate\n" );
				return $candidate;
			}
		}

		# Match headers
		foreach ( $headersRegExps as $regExp => $candidate ) {
			if ( preg_match( $regExp, $head ) ) {
				wfDebug( __METHOD__ .
					": $file head recognized by regexp as $candidate\n" );
				return $candidate;
			}
		}

		return false;
	}

	/**
	 * Hook: MimeMagicGuessFromContent.
	 *
	 * @param MimeAnalyzer $mimeMagic
	 * @param string &$head
	 * @param string &$tail
	 * @param string $file
	 * @param string &$mime
	 * @return bool Always true
	 */
	public static function onMimeMagicGuessFromContent(
		$mimeMagic,
		&$head, &$tail,
		$file, &$mime
	) {
		$headLen = strlen( $head );
		$headHead = substr( $head, 0, min( $headLen, 1024 ) );
		$tailLen = strlen( $tail );
		$tailTail = substr( $tail, max( $tailLen - 1024, 0 ), min( 1024, $tailLen ) );

		$mime = self::doGuessChemicalMime( $headHead, $tailTail, $file );
		return true;
	}
}
