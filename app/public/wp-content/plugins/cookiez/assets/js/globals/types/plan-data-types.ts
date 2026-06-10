type PlanFeatures = {
	cookie_scans: number;
	cookie_consents: number;
};

type Plan = {
	subscription_id: string;
	next_cycle_date: string;
	status: string;
	name: string;
	features: PlanFeatures;
};

type PlanUser = {
	email: string;
	id: string;
};

type PlanSubscription = {
	id: string;
};

type PlanSite = {
	registered_at: string;
};

type QuotaEntry = {
	allowed: number;
	used: number;
};

type PlanQuota = {
	cookie_consents: QuotaEntry;
	cookie_scans: QuotaEntry;
};

export type PlanData = {
	plan: Plan;
	site_url: string;
	scopes: string[];
	public_api_key: string;
	user: PlanUser;
	subscription: PlanSubscription;
	site: PlanSite;
	quota?: PlanQuota;
};
