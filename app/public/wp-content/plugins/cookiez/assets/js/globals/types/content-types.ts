import TemplateType from '@cookiez/globals/enums/template-type';

import { CookieCategory } from './cookie-types';

export type CategoryData = {
	title: string;
	description: string;
};

export type CookieCategories = Record<CookieCategory, CategoryData>;

export type PreferencesContent = {
	header: string;
	privacyOverview: string;
	acceptAllLabel: string;
	denyLabel: string;
	savePreferencesLabel: string;
	consentLabel?: string;
};

export type OptOutDialogContent = {
	header: string;
	privacyOverview: string;
	consentLabel: string;
	denyLabel: string;
	savePreferencesLabel: string;
};

export type BannerContent = {
	title: string;
	cookieMessage: string;
	acceptAllLabel: string;
	denyLabel: string;
	customizeLabel: string;
	cookiePolicyLink: boolean;
	cookiePolicyLinkText: string;
	cookiePolicyUrl: string;
	privacyPolicyLink: boolean;
	privacyPolicyLinkText: string;
	privacyPolicyUrl: string;
	doNotSell: boolean;
	doNotSellLinkText: string;
};

export type ContentTemplateType = TemplateType.OptIn | TemplateType.OptOut;

export type ParsedContent = {
	banner: BannerContent;
	preferences: PreferencesContent;
	categories: CookieCategories;
};

export type OptInData = {
	banner: BannerContent;
	preferences: PreferencesContent;
	categories: CookieCategories;
};

export type OptOutData = {
	banner: BannerContent;
	optOutDialog: OptOutDialogContent;
};

export type LanguageData = {
	[TemplateType.OptIn]: OptInData;
	[TemplateType.OptOut]: OptOutData;
};

export type LanguageDataMergePatch = {
	[TemplateType.OptIn]?: {
		banner?: Partial<BannerContent>;
		preferences?: Partial<PreferencesContent>;
		categories?: Partial<CookieCategories>;
	};
	[TemplateType.OptOut]?: {
		banner?: Partial<BannerContent>;
		optOutDialog?: Partial<OptOutDialogContent>;
	};
};
