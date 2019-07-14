<?php
/**
 * Tests for MolHandler's MIME type detection
 *
 * @file
 * @ingroup Extensions
 *
 * @author Rillke
 */

class MolHandlerMimeFunctionsTest extends MediaWikiTestCase {
	/**
	 * Hook: Runs the tests over the provided files.
	 *
	 * @param array $files
	 * @param bool $shouldEqual
	 * @return null
	 */
	private function runTestMime( $files, $shouldEqual ) {
		foreach ( $files as $filePath ) {
			# Read file contents to memory
			$handle = fopen( $filePath, 'rb' );
			if ( !$handle ) {
				return $this->fail( "Cannot open $filePath" );
			}

			$fsize = filesize( $filePath );
			if ( $fsize === false ) {
				return $this->fail( "Cannot get file size of $filePath" );
			}

			$head = fread( $handle, 1024 );

			$tailLength = min( 65558, $fsize );
			if ( fseek( $handle, -1 * $tailLength, SEEK_END ) === -1 ) {
				return $this->fail(
				"Seeking $tailLength bytes from EOF failed in $filePath" );
			}
			$tail = fread( $handle, $tailLength );
			fclose( $handle );

			# Set up prerequisites
			$mimeMagic = \MediaWiki\MediaWikiServices::getInstance()->getMimeAnalyzer();
			$mimeByExt = 'text/plain';
			$ext = strtolower( preg_replace( '/.*\.(\w{2,})$/', '\1', $filePath ) );

			# Run the tests
			$mimeByContent = MolHandlerMime::doGuessChemicalMime( $head, $tail, $filePath );
			$mimeByExt = MolHandlerMime::improveFromExtension(
				$mimeMagic, $ext, $mimeByExt
			);

			# Execute the assertion
			if ( $shouldEqual ) {
				$this->assertTrue( $mimeByContent === $mimeByExt,
					"MIME detected by content sniffing ($mimeByContent) should equal " .
					"MIME deduced from the file extension ($mimeByExt) in $filePath."
				);
			} else {
				$this->assertTrue( $mimeByContent !== $mimeByExt,
					"MIME detected by content sniffing ($mimeByContent) should *not* equal " .
					"MIME deduced from the file extension ($mimeByExt) in $filePath."
				);
			}
		}
	}

	/**
	* @group MolHandler
	* @group MimeTypeDetection
	* @group Multimedia
	* @covers MolHandlerMime::doGuessChemicalMime
	* @covers MolHandlerMime::improveFromExtension
	* @uses MimeAnalyzer
	* @group medium
	*/
	function testMimeDetectionByContentAndFileExtension() {
		$fileDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'files';
		$files = glob( $fileDir . DIRECTORY_SEPARATOR . '*.test.pass.*' );

		self::runTestMime( $files, true );
	}
}
