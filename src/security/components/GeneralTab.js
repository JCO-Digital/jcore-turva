import { useState, useEffect } from "@wordpress/element";
import {
	Button,
	Panel,
	PanelBody,
	PanelRow,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

const REFERRER_OPTIONS = [
	{
		value: "no-referrer",
		label: __("No referrer", "jcore-turva"),
	},
	{
		value: "no-referrer-when-downgrade",
		label: __("No referrer when downgrade", "jcore-turva"),
	},
	{
		value: "origin",
		label: __("Origin only", "jcore-turva"),
	},
	{
		value: "origin-when-cross-origin",
		label: __("Origin when cross-origin", "jcore-turva"),
	},
	{
		value: "same-origin",
		label: __("Same origin only", "jcore-turva"),
	},
	{
		value: "strict-origin",
		label: __("Strict origin", "jcore-turva"),
	},
	{
		value: "strict-origin-when-cross-origin",
		label: __("Strict origin when cross-origin (recommended)", "jcore-turva"),
	},
	{
		value: "unsafe-url",
		label: __(
			"Unsafe URL — sends full URL everywhere (not recommended)",
			"jcore-turva",
		),
	},
];

const CSP_MODE_OPTIONS = [
	{
		value: "disabled",
		label: __("Disabled", "jcore-turva"),
	},
	{
		value: "report-only",
		label: __("Report Only", "jcore-turva"),
	},
	{
		value: "enabled",
		label: __("Enabled (Enforced)", "jcore-turva"),
	},
];

export default function GeneralTab() {
	const [settings, setSettings] = useState(null);
	const [isSaving, setIsSaving] = useState(false);
	const [saved, setSaved] = useState(false);
	const [error, setError] = useState(null);

	useEffect(() => {
		apiFetch({ path: "/jcore-turva/v1/settings" })
			.then(setSettings)
			.catch(() => setError(__("Failed to load settings.", "jcore-turva")));
	}, []);

	if (error) {
		return <p className="jcore-turva__error">{error}</p>;
	}

	if (!settings) {
		return <Spinner />;
	}

	const update = (key, value) => setSettings({ ...settings, [key]: value });

	const save = async () => {
		setIsSaving(true);
		setSaved(false);
		setError(null);
		try {
			await apiFetch({
				path: "/jcore-turva/v1/settings",
				method: "POST",
				data: settings,
			});
			setSaved(true);
			setTimeout(() => setSaved(false), 3000);
		} catch {
			setError(__("Failed to save settings.", "jcore-turva"));
		} finally {
			setIsSaving(false);
		}
	};

	return (
		<div className="jcore-turva__tab-content">
			<Panel>
				<PanelBody
					title={__("HTTP Security Headers", "jcore-turva")}
					initialOpen={true}
				>
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={__("HTTP Strict Transport Security (HSTS)", "jcore-turva")}
							help={__(
								"Forces browsers to use HTTPS for future visits. Only enable on HTTPS sites.",
								"jcore-turva",
							)}
							checked={!!settings.hsts}
							onChange={(v) => update("hsts", v)}
						/>
					</PanelRow>
					{settings.hsts && (
						<PanelRow>
							<TextControl
								__nextHasNoMarginBottom
								label={__("Max Age (seconds)", "jcore-turva")}
								help={__(
									"How long browsers should remember to use HTTPS. Default: 31536000 (1 year).",
									"jcore-turva",
								)}
								type="number"
								min="0"
								value={settings.hsts_max_age ?? 31536000}
								onChange={(v) => update("hsts_max_age", parseInt(v, 10) || 0)}
							/>
						</PanelRow>
					)}
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={__("X-Content-Type-Options: nosniff", "jcore-turva")}
							help={__(
								"Prevents browsers from MIME-sniffing the declared content type.",
								"jcore-turva",
							)}
							checked={!!settings.nosniff}
							onChange={(v) => update("nosniff", v)}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={__("X-XSS-Protection", "jcore-turva")}
							help={__(
								"Legacy XSS filter for older browsers. Not needed if a strong CSP is in place.",
								"jcore-turva",
							)}
							checked={!!settings.xss_protection}
							onChange={(v) => update("xss_protection", v)}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={__("Referrer-Policy", "jcore-turva")}
							help={__(
								"Controls how much referrer information is included with requests.",
								"jcore-turva",
							)}
							checked={!!settings.referrer_policy}
							onChange={(v) => update("referrer_policy", v)}
						/>
					</PanelRow>
					{settings.referrer_policy && (
						<PanelRow>
							<SelectControl
								__nextHasNoMarginBottom
								label={__("Policy value", "jcore-turva")}
								value={
									settings.referrer_value ?? "strict-origin-when-cross-origin"
								}
								options={REFERRER_OPTIONS}
								onChange={(v) => update("referrer_value", v)}
							/>
						</PanelRow>
					)}
				</PanelBody>
				<PanelBody
					title={__("Content Security Policy", "jcore-turva")}
					initialOpen={true}
				>
					<PanelRow>
						<SelectControl
							__nextHasNoMarginBottom
							label={__("CSP Mode", "jcore-turva")}
							help={__(
								"Control how the Content Security Policy is applied.",
								"jcore-turva",
							)}
							value={settings.csp_mode ?? "enabled"}
							options={CSP_MODE_OPTIONS}
							onChange={(v) => update("csp_mode", v)}
						/>
					</PanelRow>
					{(settings.csp_mode ?? "enabled") === "enabled" && (
						<PanelRow>
							<ToggleControl
								__nextHasNoMarginBottom
								label={__("CSP Test Mode", "jcore-turva")}
								help={__(
									"Sends Content-Security-Policy-Report-Only for administrators. Useful when adding new resources to the page.",
									"jcore-turva",
								)}
								checked={!!settings.csp_test_mode}
								onChange={(v) => update("csp_test_mode", v)}
							/>
						</PanelRow>
					)}
				</PanelBody>
			</Panel>
			<div className="jcore-turva__actions">
				<Button
					variant="primary"
					onClick={save}
					isBusy={isSaving}
					disabled={isSaving}
				>
					{__("Save Settings", "jcore-turva")}
				</Button>
				{saved && (
					<span className="jcore-turva__saved">
						{__("Settings saved.", "jcore-turva")}
					</span>
				)}
				{error && <span className="jcore-turva__error">{error}</span>}
			</div>
		</div>
	);
}
