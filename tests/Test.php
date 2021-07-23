<?php
declare(strict_types=1);

require __DIR__ . '/FileDetector.php';

$Detector = new FileDetector( __DIR__ . '/../rules.ini' );

$TestsIterator = new DirectoryIterator( __DIR__ . '/types' );

$SeenTestTypes = [];
$FailingTests = [];
$PassedTests = 0;
$TotalTestsRun = 0;

foreach( $TestsIterator as $File )
{
	if( $File->getExtension() !== 'txt' )
	{
		continue;
	}

	$TestFilePaths = file( $File->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$ExpectedType = $File->getBasename( '.txt' );

	if( $ExpectedType === '_NonMatchingTests' )
	{
		$ExpectedType = null;
	}

	$AlreadySeenStrings = [];

	foreach( $TestFilePaths as $Path )
	{
		if( isset( $AlreadySeenStrings[ $Path ] ) )
		{
			$FailingTests[] = "Path \"$Path\" in \"$File\" is defined more than once";
		}

		$AlreadySeenStrings[ $Path ] = true;

		$TotalTestsRun++;
		$Actual = $Detector->GetMatchingRuleForFilePath( $Path );

		if( preg_last_error() !== PREG_NO_ERROR )
		{
			throw new RuntimeException( 'PCRE returned an error: ' . preg_last_error() . ' - ' . preg_last_error_msg() );
		}

		if( $Actual === $ExpectedType )
		{
			$PassedTests++;
			continue;
		}

		if( $ExpectedType === null )
		{
			if( strstr($Actual, "Evidence.") !== false )
			{
				//Evidence tests get ignored when matching non-matching tests
				$PassedTests++;
				continue;
			}
			else
			{
				$FailingTests[] = "Path \"$Path\" returned \"$Actual\" but it should not have matched anything";
			}
		}
		else
		{
			$FailingTests[] = "Path \"$Path\" does not match for \"$ExpectedType\"";
		}
	}

	if( !empty( $TestFilePaths ) )
	{
		$SeenTestTypes[ $ExpectedType ] = true;
	}
}

foreach( $Detector->Map as $TestType )
{
	if( !isset( $SeenTestTypes[ $TestType ] ) )
	{
		$FailingTests[] = "\"$TestType\" does not have any tests";
	}
}

echo "{$PassedTests} tests out of {$TotalTestsRun} tests passed.\n";

if( !empty( $FailingTests ) )
{
	err( count( $FailingTests ) . " tests failed:" );

	foreach( $FailingTests as $Test )
	{
		err( $Test );
	}

	exit( 1 );
}

function err( string $Message ) : void
{
	if( getenv( 'CI' ) !== false )
	{
		echo "::error::" . $Message . PHP_EOL;
	}
	else
	{
		fwrite( STDERR, $Message . PHP_EOL );
	}
}