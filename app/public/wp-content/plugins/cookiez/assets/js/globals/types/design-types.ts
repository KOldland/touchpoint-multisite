export enum BannerType {
	Banner = 'banner',
	Box = 'box',
}

export type VerticalPosition = 'bottom' | 'top';

export type HorizontalPosition =
	| 'bottom-left'
	| 'bottom-center'
	| 'bottom-right';

export type Viewport = 'desktop' | 'mobile';

export type HorizontalDirection = 'left' | 'right';

export type VerticalDirection = 'higher' | 'lower';

export type ButtonSize = 'small' | 'medium' | 'large';

export type ConsentIconType = 'cookie' | 'text';

export type ConsentIconSize = 'small' | 'medium' | 'large';

export type ConsentIconPositionDesktopType =
	| 'top-left'
	| 'top-center'
	| 'top-right'
	| 'center-left'
	| 'center-right'
	| 'bottom-left'
	| 'bottom-center'
	| 'bottom-right';

export type ConsentIconPositionMobileType =
	| 'top-left'
	| 'top-right'
	| 'center-left'
	| 'center-right'
	| 'bottom-left'
	| 'bottom-right';

export type DesignState = {
	bannerType: BannerType;
	bannerPositionDesktop: VerticalPosition;
	bannerPositionMobile: VerticalPosition;
	bannerCornerRadius: number;
	bannerBackgroundColor: string;
	bannerTextColor: string;
	bannerLinkColor: string;
	titleTextSize: number;
	bodyTextSize: number;
	buttonSize: ButtonSize;
	buttonsAndTogglesColor: string;
	buttonsCornerRadius: number;
	hidePoweredBy: boolean;
	customPositionDesktopEnabled: boolean;
	customPositionDesktopHorizontalOffset: number;
	customPositionDesktopHorizontalDirection: HorizontalDirection;
	customPositionDesktopVerticalOffset: number;
	customPositionDesktopVerticalDirection: VerticalDirection;
	customPositionMobileEnabled: boolean;
	customPositionMobileHorizontalOffset: number;
	customPositionMobileHorizontalDirection: HorizontalDirection;
	customPositionMobileVerticalOffset: number;
	customPositionMobileVerticalDirection: VerticalDirection;
	boxPositionDesktop: HorizontalPosition;
	boxPositionMobile: VerticalPosition;
	customPositionBoxDesktopEnabled: boolean;
	customPositionBoxDesktopHorizontalOffset: number;
	customPositionBoxDesktopHorizontalDirection: HorizontalDirection;
	customPositionBoxDesktopVerticalOffset: number;
	customPositionBoxDesktopVerticalDirection: VerticalDirection;
	customPositionBoxMobileEnabled: boolean;
	customPositionBoxMobileHorizontalOffset: number;
	customPositionBoxMobileHorizontalDirection: HorizontalDirection;
	customPositionBoxMobileVerticalOffset: number;
	customPositionBoxMobileVerticalDirection: VerticalDirection;
	consentIconActivation: boolean;
	consentIconType: ConsentIconType;
	consentIconSize: ConsentIconSize;
	consentIconColor: string;
	consentIconCornerRadius: number;
	consentIconPositionDesktop: ConsentIconPositionDesktopType;
	consentIconPositionMobile: ConsentIconPositionMobileType;
	customPositionIconDesktopEnabled: boolean;
	customPositionIconDesktopHorizontalOffset: number;
	customPositionIconDesktopHorizontalDirection: HorizontalDirection;
	customPositionIconDesktopVerticalOffset: number;
	customPositionIconDesktopVerticalDirection: VerticalDirection;
	customPositionIconMobileEnabled: boolean;
	customPositionIconMobileHorizontalOffset: number;
	customPositionIconMobileHorizontalDirection: HorizontalDirection;
	customPositionIconMobileVerticalOffset: number;
	customPositionIconMobileVerticalDirection: VerticalDirection;
};
