<?php
class MolHandlerHooks {
	/**
	 * Hook to add unit tests
	 * @param array $files list of testcases
	 * @return bool
	 */
	public static function onUnitTestsList( array &$files ) {
		$testDir = __DIR__ . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'phpunit';
		$files = array_merge( $files, glob( $testDir . DIRECTORY_SEPARATOR . '*Test.php' ) );
		return true;
	}
}
