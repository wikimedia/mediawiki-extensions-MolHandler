<?php
/**
 * Handler for Chemical table files
 *
 * Reusing some code from SvgHandler
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

abstract class MolHandler extends SvgHandler {
	const FILE_FORMAT = 'mol';

	/**
	 * @param File $file
	 * @return bool
	 */
	function isAnimatedImage( $file ) {
		return false;
	}

	/**
	 * True, if the mol converter specified by $wgMolConverter is
	 * able to render the currently chosen file type.
	 * @return bool
	 */
	private function molConverterIsCapable() {
		global $wgMolConverter, $wgMolConvertCommands;

		$formats = $wgMolConvertCommands[$wgMolConverter]['supportedFormats'];
		return in_array( static::FILE_FORMAT, $formats );
	}

	/**
	 * True if the handled types can be transformed
	 *
	 * @param File $file
	 * @return bool
	 */
	function canRender( $file ) {
		return $this->molConverterIsCapable() && parent::canRender( $file );
	}

	/**
	 * @param File $image
	 * @param string $dstPath
	 * @param string $dstUrl
	 * @param array $params
	 * @param int $flags
	 * @return bool|MediaTransformError|ThumbnailImage|TransformParameterError
	 */
	function doTransform( $image, $dstPath, $dstUrl, $params, $flags = 0 ) {
		if ( !$this->normaliseParams( $image, $params ) ) {
			return new TransformParameterError( $params );
		}
		$clientWidth = $params['width'];
		$clientHeight = $params['height'];
		$physicalWidth = $params['physicalWidth'];
		$physicalHeight = $params['physicalHeight'];

		if ( $flags & self::TRANSFORM_LATER ) {
			return new ThumbnailImage( $image, $dstUrl, $dstPath, $params );
		}

		$metadata = $this->unpackMetadata( $image->getMetadata() );
		if ( isset( $metadata['error'] ) ) { // sanity check
			$err = wfMessage(
				'svg-long-error',
				$metadata['error']['message']
			)->text();

			return new MediaTransformError(
				'thumbnail_error',
				$clientWidth,
				$clientHeight,
				$err
			);
		}

		# It is important that the SVG file created ends with it's real name
		# otherwise it won't be purged
		$svgThumbPath = $image->getThumbPath( 'molhandler-' . $image->getName() );
		$svgPath = $dstPath . '.svg';

		if ( !wfMkdirParents( dirname( $dstPath ), null, __METHOD__ )
			|| !wfMkdirParents( dirname( $svgPath ), null, __METHOD__ )
		) {
			return new MediaTransformError(
				'thumbnail_error',
				$clientWidth,
				$clientHeight,
				wfMessage( 'thumbnail_dest_directory' )->text()
			);
		}

		# Check whether there is already a SVG generated
		# treating the SVG as "thumbnail" although it isn't
		$repo = $image->getRepo();
		$tempFSFile = null;
		$thumbExists = $repo->fileExists( $svgThumbPath );
		if ( $thumbExists ) {
			# Copy over the file from remote as processing locally
			# will be possibly faster, dependent on the setup and
			# converter used. It will be fetched by the converter
			# anyway.
			$tempFSFile = $repo->getLocalCopy( $svgThumbPath );
			$svgPath = $tempFSFile->getPath();
			wfDebug( "SVG thumb exists at $svgPath. Re-using.\n" );
		}

		$srcPath = $image->getLocalRefPath();
		$status = $this->rasterizeCTF(
			$srcPath,
			$dstPath,
			$physicalWidth,
			$physicalHeight,
			$svgPath
		);

		if ( $tempFSFile ) {
			# Clean up temporary file
			$tempFSFile->purge();
		} elseif ( is_file( $svgPath ) ) {
			if ( filesize( $svgPath ) > 0 ) {
				$repoStatus = $repo->quickImport( $svgPath, $svgThumbPath );
				if ( !$repoStatus->isGood() ) {
					wfDebug( "Cannot copy SVG file ($svgPath) to repo ($svgThumbPath) " .
						"because {$repoStatus->getHTML()} \n" );
				}
			}

			# Clean up
			unlink( $svgPath );
		}

		if ( $status === true ) {
			return new ThumbnailImage( $image, $dstUrl, $dstPath, $params );
		}

		return $status; // MediaTransformError
	}

	/**
	 * Transform a Chemical table file to PNG
	 * Overriding default implementation for compatibility
	 * Not in use by this class or classed derived from MolHandler
	 *
	 * This function can be called outside of thumbnail contexts
	 * @param string $srcPath
	 * @param string $dstPath
	 * @param string $width
	 * @param string $height
	 * @param bool|string $lang Language code of the language to render the SVG in
	 * @throws MWException
	 * @return bool|MediaTransformError
	 */
	public function rasterize( $srcPath, $dstPath, $width, $height, $lang = false ) {
		$svgPath = $dstPath . '.svg';
		$result = $this->rasterizeCTF( $srcPath, $dstPath,  $width, $height, $svgPath );

		if ( file_exists( $svgPath ) && filesize( $svgPath ) > 0 ) {
			unlink( $svgPath );
		}
		return $result;
	}

	/**
	 * Transform a Chemical table file (CTF) to SVG and SVG to PNG
	 * @param string $srcPath
	 * @param string $dstPath
	 * @param string $width
	 * @param string $height
	 * @param string $svgPath
	 * @throws MWException
	 * @return bool|MediaTransformError
	 */
	private function rasterizeCTF( $srcPath, $dstPath, $width, $height, $svgPath ) {
		# Create SVG if it does not yet exist, otherwise just modify the $srcPath

		$result = $this->execMolConverter( $svgPath, $srcPath, $width, $height );
		if ( $result !== 0 ) {
			return $result;
		}

		# Finally let our parents do the work for us :)
		return parent::rasterize( $svgPath, $dstPath, $width, $height );
	}

	/**
	 * Transform a Chemical table file (CTF) to SVG
	 * @param string $svgPath
	 * @param string $srcPath
	 * @param string $width Optional desired width (used for error reporting only)
	 * @param string $height Optional desired height (used for error reporting only)
	 * @throws MWException
	 * @return bool|MediaTransformError
	 */
	private function execMolConverter( $svgPath, $srcPath, $width = 0, $height = 0 ) {
		global $wgMolConverterPath;

		$err = false;
		$converter = $this->getMolConverter();
		$retval = '';
		$limits = [
			'memory' => $converter['memory']
		];

		if ( !file_exists( $svgPath ) ) {
			// External command
			$cmd = str_replace(
				[ '$path/', '$format', '$input', '$output' ],
				[ $wgMolConverterPath
					? wfEscapeShellArg( "$wgMolConverterPath/" )
					: "",
						wfEscapeShellArg( static::FILE_FORMAT ),
						wfEscapeShellArg( $srcPath ),
						wfEscapeShellArg( $svgPath )
				],
				$converter['command']
			);

			wfDebug( __METHOD__ . ": $cmd\n" );
			$err = wfShellExecWithStderr( $cmd, $retval, [], $limits );
		}

		$removed = $this->removeBadFile( $svgPath, $retval );
		if ( $retval != 0 || $removed ) {
			$this->logErrorForExternalProcess( $retval, $err, $cmd );
			return new MediaTransformError( 'thumbnail_error', $width, $height, $err );
		}

		return 0;
	}

	/**
	 * Get the command for the default molconverter
	 *
	 * @return array Mol converter
	 */
	private function getMolConverter() {
		global $wgMolConvertCommands, $wgMolConverter;

		if ( !$this->molConverterIsCapable() ) {
			throw new MWException(
				'Converting ' . static::FILE_FORMAT . ' to SVG ' .
				'is not supported by the currently chosen MolConverter.'
			);
		}

		return $wgMolConvertCommands[$wgMolConverter];
	}

	/**
	 * Extract Metadata from file
	 * Note that is requires the converter being installed on
	 * all machines, not just the image scalers
	 *
	 * @param File $file
	 * @param string $filename
	 * @return string Serialised metadata
	 */
	function getMetadata( $file, $filename ) {
		# As this is the server's local temp directory, it should be okay to assume
		# we can write into it
		$svgfilename = $filename . '.svg';
		$tmpFilename = $filename . '.' . static::FILE_FORMAT;

		# Make sure the chmical table file has the right file extension
		# and not to touch the file provided by MW
		copy( $filename, $tmpFilename );

		$result = $this->execMolConverter( $svgfilename, $tmpFilename );

		# Remove temporary file we previously created
		unlink( $tmpFilename );

		if ( $result !== 0 ) {
			return $result;
		}

		# Finally defer the work to our parents :)
		$meta = parent::getMetadata( $file, $svgfilename );

		# And clean up
		unlink( $svgfilename );
		return $meta;
	}

	/**
	 * Subtitle for the image
	 * TODO: Create custom subtitle as the SVG-one is not suitable.
	 *
	 * @param File $file
	 * @return string
	 */
	function getLongDesc( $file ) {
		return ImageHandler::getLongDesc( $file );
	}
}
