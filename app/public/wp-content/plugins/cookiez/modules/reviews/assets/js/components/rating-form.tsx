import FormControl from '@elementor/ui/FormControl';
import FormControlLabel from '@elementor/ui/FormControlLabel';
import ListItem from '@elementor/ui/ListItem';
import ListItemIcon from '@elementor/ui/ListItemIcon';
import Radio from '@elementor/ui/Radio';
import RadioGroup from '@elementor/ui/RadioGroup';
import { styled } from '@elementor/ui/styles';
import { __ } from '@wordpress/i18n';

import { useReviewSettings } from '../hooks/use-review-settings';
import {
	MoodEmpty,
	MoodHappy,
	MoodSad,
	MoodSadSquint,
	MoodSmile,
} from '../icons';

type RatingMapItem = {
	value: number;
	label: string;
	icon: JSX.Element;
};

const RatingForm = () => {
	const { isSubmitting, selectRating, submitted } = useReviewSettings();

	const ratingsMap: RatingMapItem[] = [
		{
			value: 5,
			label: __('Excellent', 'cookiez'),
			icon: <MoodHappy />,
		},
		{
			value: 4,
			label: __('Pretty good', 'cookiez'),
			icon: <MoodSmile />,
		},
		{
			value: 3,
			label: __("It's okay", 'cookiez'),
			icon: <MoodEmpty />,
		},
		{
			value: 2,
			label: __('Could be better', 'cookiez'),
			icon: <MoodSadSquint />,
		},
		{
			value: 1,
			label: __('Needs improvement', 'cookiez'),
			icon: <MoodSad />,
		},
	];

	const handleRatingChange = (_event: unknown, value: string) => {
		const ratingValue = Number.parseInt(value, 10);
		void selectRating(ratingValue);
	};

	return (
		<FormControl fullWidth>
			<RadioGroup
				aria-labelledby="demo-radio-buttons-group-label"
				onChange={handleRatingChange}
				name="radio-buttons-group"
			>
				{ratingsMap.map(({ value, label, icon }) => (
					<ListItem key={`item-${value}`} disableGutters disablePadding>
						<ListItemIcon>{icon}</ListItemIcon>
						<StyledFormControlLabel
							control={<Radio color="secondary" />}
							label={label}
							value={value}
							labelPlacement="start"
							disabled={isSubmitting || submitted}
						/>
					</ListItem>
				))}
			</RadioGroup>
		</FormControl>
	);
};

export default RatingForm;

const StyledFormControlLabel = styled(FormControlLabel)`
	justify-content: space-between;
	margin-inline-start: 0;
	width: 100%;
`;
