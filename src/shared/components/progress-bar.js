/**
 * A thin determinate progress bar in the admin theme color.
 *
 * @param {Object} props
 * @param {number} props.value Percentage, 0 to 100.
 */
export default function ProgressBar( { value } ) {
	return (
		<span
			className="vlfm-progress"
			role="progressbar"
			aria-valuemin={ 0 }
			aria-valuemax={ 100 }
			aria-valuenow={ value }
		>
			<span
				className="vlfm-progress__bar"
				style={ { width: `${ value }%` } }
			/>
		</span>
	);
}
