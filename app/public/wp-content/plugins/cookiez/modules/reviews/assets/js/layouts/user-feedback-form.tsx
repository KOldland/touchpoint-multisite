import Box from '@elementor/ui/Box';
import Popover from '@elementor/ui/Popover';
import Typography from '@elementor/ui/Typography';
import { styled } from '@elementor/ui/styles';
import { mixpanelEvents, mixpanelService } from '@cookiez/globals';
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import DismissButton from '../components/dismiss-button';
import FeedbackForm from '../components/feedback-form';
import RatingForm from '../components/rating-form';
import ReviewForm from '../components/review-form';
import { POPOVER_ANCHOR_OFFSET_PX } from '../constants';
import DismissButtonVariant from '../enums/dismiss-button-variant';
import ReviewPage from '../enums/review-page';
import { useReviewSettings } from '../hooks/use-review-settings';

const UserFeedbackForm = () => {
	const anchorEl = useRef<HTMLDivElement | null>(null);
	const { close, currentPage, isOpened } = useReviewSettings();

	const handleReviewPromptEntered = () => {
		mixpanelService.init().then(() => {
			mixpanelService.sendEvent(mixpanelEvents.reviewPromptShown, {});
		});
	};

	const id = isOpened ? 'reviews-popover' : undefined;
	const isRTL = Boolean(window.cookiezReviewData?.isRTL);
	const horizontalOrigin = isRTL ? 'left' : 'right';

	const headerMessage: Record<ReviewPage, string | null> = {
		[ReviewPage.Ratings]: __('How would you rate Cookiez so far?', 'cookiez'),
		[ReviewPage.Feedback]: __(
			'We’re thrilled to hear that! What would make it even better?',
			'cookiez',
		),
		[ReviewPage.Review]: null,
	};

	return (
		<Popover
			open={isOpened}
			TransitionProps={{ onEntered: handleReviewPromptEntered }}
			anchorOrigin={{ vertical: 'bottom', horizontal: horizontalOrigin }}
			anchorReference="anchorPosition"
			anchorPosition={{
				top: window.innerHeight - POPOVER_ANCHOR_OFFSET_PX,
				left: isRTL
					? POPOVER_ANCHOR_OFFSET_PX
					: window.innerWidth - POPOVER_ANCHOR_OFFSET_PX,
			}}
			transformOrigin={{
				vertical: 'bottom',
				horizontal: horizontalOrigin,
			}}
			id={id}
			onClose={close}
			anchorEl={anchorEl.current}
			disableEscapeKeyDown
			disableScrollLock
			disablePortal
			slotProps={{
				paper: {
					sx: {
						pointerEvents: 'auto',
					},
				},
			}}
			sx={{
				pointerEvents: 'none',
			}}
		>
			<StyledBox>
				<StyledHeader>
					<Typography
						variant="subtitle1"
						color="text.primary"
						marginBlockStart={1}
					>
						{headerMessage[currentPage]}
					</Typography>
				</StyledHeader>
				{ReviewPage.Ratings === currentPage && <RatingForm />}
				{ReviewPage.Feedback === currentPage && <FeedbackForm />}
				{ReviewPage.Review === currentPage && <ReviewForm />}
			</StyledBox>
			<StyledFooter currentPage={currentPage}>
				<DismissButton variant={DismissButtonVariant.Button} />
			</StyledFooter>
		</Popover>
	);
};

export default UserFeedbackForm;

const StyledBox = styled(Box)`
	width: 350px;
	padding: ${({ theme }) => theme.spacing(1.5)};
`;

const StyledHeader = styled(Box)`
	display: flex;
	flex-direction: row;
	justify-content: space-between;
	align-items: center;
	margin-block-end: ${({ theme }) => theme.spacing(2)};
`;

type StyledFooterProps = {
	currentPage: ReviewPage;
};

const StyledFooter = styled(Box, {
	shouldForwardProp: (prop) => prop !== 'currentPage',
})<StyledFooterProps>`
	display: flex;
	flex-direction: row;
	justify-content: space-between;
	align-items: center;
	${({ currentPage, theme }) =>
		currentPage !== ReviewPage.Feedback &&
		`border-block-start: 1px solid ${theme.palette.divider};`}
`;
