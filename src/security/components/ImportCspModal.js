import { useState } from "@wordpress/element";
import { Modal, Button, TextareaControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

export default function ImportCspModal({ onClose, onImport }) {
	const [cspString, setCspString] = useState("");
	const [isImporting, setIsImporting] = useState(false);

	const parseCsp = (str) => {
		// Remove "Content-Security-Policy:" prefix if present
		let cleanStr = str.trim();
		if (cleanStr.toLowerCase().startsWith("content-security-policy:")) {
			cleanStr = cleanStr.substring("content-security-policy:".length).trim();
		}

		const directives = cleanStr.split(";");
		const result = [];
		directives.forEach((directiveStr) => {
			const parts = directiveStr.trim().split(/\s+/);
			if (parts.length > 0 && parts[0]) {
				const directive = parts[0].toLowerCase();
				const sources = parts.slice(1);
				if (sources.length > 0) {
					sources.forEach((source) => {
						result.push({ directive, source });
					});
				} else {
					// It could be a flag directive or just an empty directive
					// For jcore-turva, we use '1' as placeholder for flag directives
					result.push({ directive, source: "1" });
				}
			}
		});
		return result;
	};

	const handleImport = async (action) => {
		setIsImporting(true);
		const parsed = parseCsp(cspString);

		try {
			await apiFetch({
				path: "/jcore-turva/v1/sources/import",
				method: "POST",
				data: {
					header_type: "csp",
					directives: parsed,
					action, // 'merge' or 'replace'
				},
			});
			onImport();
		} catch (e) {
			alert(
				__("Import failed: ", "jcore-turva") +
					(e.message || __("Unknown error", "jcore-turva")),
			);
		} finally {
			setIsImporting(false);
		}
	};

	return (
		<Modal title={__("Import CSP", "jcore-turva")} onRequestClose={onClose}>
			<TextareaControl
				label={__("Paste CSP string here", "jcore-turva")}
				value={cspString}
				onChange={setCspString}
				rows={10}
			/>
			<div className="jcore-turva__modal-actions">
				<Button variant="secondary" onClick={onClose} disabled={isImporting}>
					{__("Cancel", "jcore-turva")}
				</Button>
				<Button
					variant="primary"
					onClick={() => handleImport("merge")}
					isBusy={isImporting}
					disabled={!cspString || isImporting}
				>
					{__("Merge CSP", "jcore-turva")}
				</Button>
				<Button
					variant="primary"
					onClick={() => handleImport("replace")}
					isBusy={isImporting}
					disabled={!cspString || isImporting}
				>
					{__("Replace CSP", "jcore-turva")}
				</Button>
			</div>
		</Modal>
	);
}
