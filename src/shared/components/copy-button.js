/**
 * A small button that copies text and says so.
 */
import { Button } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function CopyButton( { text, label } ) {
	const [ hasCopied, setHasCopied ] = useState( false );
	const ref = useCopyToClipboard( text, () => setHasCopied( true ) );

	useEffect( () => {
		if ( ! hasCopied ) {
			return;
		}
		const timer = setTimeout( () => setHasCopied( false ), 2000 );
		return () => clearTimeout( timer );
	}, [ hasCopied ] );

	return (
		<Button ref={ ref } variant="secondary" size="small">
			{ hasCopied ? __( 'Copied', 'video-library-for-mux' ) : label }
		</Button>
	);
}
