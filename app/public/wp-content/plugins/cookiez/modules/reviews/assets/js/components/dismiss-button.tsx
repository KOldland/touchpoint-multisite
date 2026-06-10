import Box from '@elementor/ui/Box';
import Button from '@elementor/ui/Button';
import CloseButton from '@elementor/ui/CloseButton';
import { styled } from '@elementor/ui/styles';
import { __ } from '@wordpress/i18n';

import DismissButtonVariant from '../enums/dismiss-button-variant';
import ReviewPage from '../enums/review-page';
import { useReviewSettings } from '../hooks/use-review-settings';

type Props = {
	variant?: DismissButtonVariant;
};

const DismissButton = ({ variant = DismissButtonVariant.Icon }: Props) => {
	const { currentPage, dismiss, isSubmitting, primarySubmit, submitFailed } =
		useReviewSettings();

	const showPrimarySubmit =
		currentPage === ReviewPage.Feedback ||
		(currentPage === ReviewPage.Ratings && submitFailed);

	const isDualActionLayout = showPrimarySubmit;

	if (DismissButtonVariant.Icon === variant) {
		return <CloseButton onClick={() => void dismiss()} />;
	}

	if (DismissButtonVariant.Button === variant) {
		return (
			<ActionsRow isDualActionLayout={isDualActionLayout}>
				<Button
					color="secondary"
					variant="text"
					fullWidth={!isDualActionLayout}
					sx={{ p: isDualActionLayout ? 0.5 : 2 }}
					onClick={() => void dismiss()}
					size="small"
				>
					{__('Not now', 'cookiez')}
				</Button>
				{showPrimarySubmit && (
					<Button
						color="secondary"
						disabled={isSubmitting}
						variant="contained"
						onClick={() => void primarySubmit()}
						size="small"
					>
						{submitFailed
							? __('Try again', 'cookiez')
							: __('Submit', 'cookiez')}
					</Button>
				)}
			</ActionsRow>
		);
	}

	return null;
};

export default DismissButton;

type ActionsRowProps = {
	isDualActionLayout: boolean;
};

const ActionsRow = styled(Box, {
	shouldForwardProp: (prop) => prop !== 'isDualActionLayout',
})<ActionsRowProps>`
	display: flex;
	flex-direction: row;
	gap: ${({ theme }) => theme.spacing(1)};
	padding: ${({ isDualActionLayout, theme }) =>
		theme.spacing(isDualActionLayout ? 2 : 0)};
	width: 100%;
	justify-content: end;
`;
