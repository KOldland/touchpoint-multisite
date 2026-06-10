import StarFilledIcon from '@elementor/icons/StarFilledIcon';
import FormControl from '@elementor/ui/FormControl';
import Rating from '@elementor/ui/Rating';
import Typography from '@elementor/ui/Typography';
import { styled } from '@elementor/ui/styles';
import { __ } from '@wordpress/i18n';

import { useReviewSettings } from '../hooks/use-review-settings';
import { MoodHappy } from '../icons';

const ReviewForm = () => {
	const { redirectToWpReview } = useReviewSettings();

	return (
		<StyledFormControl fullWidth>
			<StyledMoodHappy />
			<Typography variant="h6" marginBlockEnd={1}>
				{__('Awesome!', 'cookiez')}
			</Typography>
			<Typography
				variant="body1"
				color="secondary"
				marginBlockEnd={3}
				width="55%"
			>
				{__('Help others discover Cookiez on WordPress.org', 'cookiez')}
			</Typography>
			<Rating
				emptyIcon={<StarFilledIcon fontSize="large" />}
				icon={<StarFilledIcon fontSize="large" />}
				onChange={() => void redirectToWpReview()}
				sx={{ marginBlockEnd: 3 }}
				highlightSelectedOnly={false}
			/>
		</StyledFormControl>
	);
};

export default ReviewForm;

const StyledFormControl = styled(FormControl)`
	display: flex;
	align-items: center;
	gap: ${({ theme }) => theme.spacing(1)};
	text-align: center;
`;

const StyledMoodHappy = styled(MoodHappy)`
	padding: ${({ theme }) => theme.spacing(1.5)};
	background-color: ${({ theme }) => theme.palette.action.hover};
	border-radius: ${({ theme }) => theme.shape.borderRadius * 2}px;
	font-size: 24px;
`;
