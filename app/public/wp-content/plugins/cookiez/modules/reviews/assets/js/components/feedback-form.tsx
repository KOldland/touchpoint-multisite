import FormControl from '@elementor/ui/FormControl';
import TextField from '@elementor/ui/TextField';
import { styled } from '@elementor/ui/styles';
import { __ } from '@wordpress/i18n';

import { useReviewSettings } from '../hooks/use-review-settings';

const FeedbackForm = () => {
	const { feedback, setFeedback } = useReviewSettings();

	return (
		<FormControl fullWidth>
			<StyledTextField
				onChange={(e) => setFeedback(e.target.value)}
				minRows={5}
				multiline
				placeholder={__(
					'Share your thoughts on how we can improve Cookiez …',
					'cookiez',
				)}
				value={feedback}
				color="secondary"
			/>
		</FormControl>
	);
};

export default FeedbackForm;

const StyledTextField = styled(TextField)`
	textarea:focus,
	textarea:active {
		outline: none;
		box-shadow: none;
	}
`;
