import { useState, useEffect, useCallback } from "@wordpress/element";
import {
	Button,
	Modal,
	SelectControl,
	Spinner,
	TextControl,
} from "@wordpress/components";
import { check } from "@wordpress/icons";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import { CSP_DIRECTIVES, PERMISSIONS_DIRECTIVES } from "../constants";
import { normalizeBlockedUri } from "../utils";

const extractDirective = (violatedDirective) =>
	violatedDirective?.split(" ")[0] ?? "";

const formatDate = (dateString) => {
	if (!dateString) return "-";
	return new Intl.DateTimeFormat(undefined, {
		dateStyle: "medium",
		timeStyle: "short",
	}).format(new Date(dateString));
};

function ReviewModal({ report, onClose, onReviewed }) {
	const [headerType, setHeaderType] = useState("csp");
	const [directive, setDirective] = useState(() =>
		extractDirective(report.violated_directive),
	);
	const [source, setSource] = useState(() =>
		normalizeBlockedUri(report.blocked_uri),
	);
	const [isSubmitting, setIsSubmitting] = useState(false);
	const [error, setError] = useState(null);

	const directives =
		headerType === "csp" ? CSP_DIRECTIVES : PERMISSIONS_DIRECTIVES;

	const directiveOptions = [
		{ value: "", label: __("— Select directive —", "jcore-turva") },
		...directives.map((d) => ({ value: d, label: d })),
	];

	// Reset directive when header type changes if it's no longer in the list.
	useEffect(() => {
		if (directive && !directives.includes(directive)) {
			setDirective("");
		}
	}, [headerType]); // eslint-disable-line react-hooks/exhaustive-deps

	const handleSubmit = async () => {
		if (!directive || !source.trim()) return;
		setIsSubmitting(true);
		setError(null);
		try {
			const trimmedSource = source.trim();

			// Fetch existing sources to determine whether this directive already exists.
			const existingSources = await apiFetch({
				path: addQueryArgs("/jcore-turva/v1/sources", {
					header_type: headerType,
				}),
			});

			const directiveExists = existingSources.some(
				(s) => s.directive === directive,
			);

			// Seed a new CSP directive with the current default-src sources so it
			// starts no more restrictive than the fallback policy.
			if (!directiveExists && headerType === "csp") {
				const defaultSrcSources = existingSources
					.filter((s) => s.directive === "default-src" && s.enabled)
					.map((s) => s.source);
				const seedValues =
					defaultSrcSources.length > 0 ? defaultSrcSources : ["'self'"];
				// Exclude the source we're about to add explicitly to avoid duplicates.
				const seedsToAdd = seedValues.filter((v) => v !== trimmedSource);
				await Promise.all(
					seedsToAdd.map((value) =>
						apiFetch({
							path: "/jcore-turva/v1/sources",
							method: "POST",
							data: {
								header_type: headerType,
								directive,
								source: value,
								enabled: true,
							},
						}),
					),
				);
			}

			await apiFetch({
				path: "/jcore-turva/v1/sources",
				method: "POST",
				data: {
					header_type: headerType,
					directive,
					source: trimmedSource,
					enabled: true,
				},
			});
			await apiFetch({
				path: `/jcore-turva/v1/reports/${report.id}/archive`,
				method: "POST",
			});
			onReviewed(report.id);
		} catch {
			setError(__("Failed to add source. Please try again.", "jcore-turva"));
			setIsSubmitting(false);
		}
	};

	return (
		<Modal
			title={__("Add as allowed source", "jcore-turva")}
			onRequestClose={onClose}
			className="jcore-turva__review-modal"
		>
			<div className="jcore-turva__review-form">
				<table className="jcore-turva__report-detail">
					<tbody>
						<tr>
							<th>{__("Violated directive", "jcore-turva")}</th>
							<td>
								<code>{report.violated_directive}</code>
							</td>
						</tr>
						<tr>
							<th>{__("Blocked URI", "jcore-turva")}</th>
							<td>
								<code>{report.blocked_uri}</code>
							</td>
						</tr>
						<tr>
							<th>{__("Document URIs", "jcore-turva")}</th>
							<td>
								{report.uris?.length > 0 ? (
									<ul className="jcore-turva__uri-list">
										{report.uris.map((uri) => (
											<li key={uri}>
												<code>{uri}</code>
											</li>
										))}
									</ul>
								) : (
									<code>{report.document_uri}</code>
								)}
							</td>
						</tr>
						<tr>
							<th>{__("Occurrences", "jcore-turva")}</th>
							<td>{report.report_count}</td>
						</tr>
					</tbody>
				</table>
				<hr className="jcore-turva__review-divider" />
				<p className="description">
					{__(
						"Edit the directive and source value below as needed before adding to your policy.",
						"jcore-turva",
					)}
				</p>
				<SelectControl
					__nextHasNoMarginBottom
					label={__("Header", "jcore-turva")}
					value={headerType}
					options={[
						{ value: "csp", label: "Content-Security-Policy" },
						{ value: "permissions", label: "Permissions-Policy" },
					]}
					onChange={setHeaderType}
				/>
				<SelectControl
					__nextHasNoMarginBottom
					label={__("Directive", "jcore-turva")}
					value={directive}
					options={directiveOptions}
					onChange={setDirective}
				/>
				<TextControl
					__nextHasNoMarginBottom
					label={__("Source value", "jcore-turva")}
					value={source}
					onChange={setSource}
					help={__(
						"Edit the value to allow, e.g. 'self', 'unsafe-inline', or https://example.com",
						"jcore-turva",
					)}
				/>
				{error && <p className="jcore-turva__error">{error}</p>}
				<div className="jcore-turva__modal-actions">
					<Button
						variant="primary"
						onClick={handleSubmit}
						isBusy={isSubmitting}
						disabled={!directive || !source.trim() || isSubmitting}
					>
						{__("Add source & archive report", "jcore-turva")}
					</Button>
					<Button variant="tertiary" onClick={onClose}>
						{__("Cancel", "jcore-turva")}
					</Button>
				</div>
			</div>
		</Modal>
	);
}

export default function ReportsTab() {
	const [statusFilter, setStatusFilter] = useState("new");
	const [reports, setReports] = useState(null);
	const [error, setError] = useState(null);
	const [reviewReport, setReviewReport] = useState(null);

	const loadReports = useCallback(() => {
		setReports(null);
		setError(null);
		apiFetch({
			path: addQueryArgs("/jcore-turva/v1/reports", {
				status: statusFilter,
			}),
		})
			.then(setReports)
			.catch(() => setError(__("Failed to load reports.", "jcore-turva")));
	}, [statusFilter]);

	useEffect(() => {
		loadReports();
	}, [loadReports]);

	const handleToggleProcessed = async (id, processed) => {
		setReports((prev) =>
			prev.map((r) => (r.id === id ? { ...r, processed } : r)),
		);
		try {
			await apiFetch({
				path: `/jcore-turva/v1/reports/${id}`,
				method: "PUT",
				data: { processed },
			});
		} catch {
			setReports((prev) =>
				prev.map((r) => (r.id === id ? { ...r, processed: !processed } : r)),
			);
		}
	};

	const handleArchive = async (id) => {
		const backup = reports;
		setReports((prev) => prev.filter((r) => r.id !== id));
		try {
			await apiFetch({
				path: `/jcore-turva/v1/reports/${id}/archive`,
				method: "POST",
			});
		} catch {
			setReports(backup);
		}
	};

	const handleUnarchive = async (id) => {
		const backup = reports;
		setReports((prev) => prev.filter((r) => r.id !== id));
		try {
			await apiFetch({
				path: `/jcore-turva/v1/reports/${id}/unarchive`,
				method: "POST",
			});
		} catch {
			setReports(backup);
		}
	};

	const handleDelete = async (id) => {
		const backup = reports;
		setReports((prev) => prev.filter((r) => r.id !== id));
		try {
			await apiFetch({
				path: `/jcore-turva/v1/reports/${id}`,
				method: "DELETE",
			});
		} catch {
			setReports(backup);
		}
	};

	const handleReviewed = (id) => {
		setReports((prev) => prev.filter((r) => r.id !== id));
		setReviewReport(null);
	};

	return (
		<div className="jcore-turva__tab-content">
			<div className="jcore-turva__filter-bar">
				<Button
					variant={statusFilter === "new" ? "primary" : "secondary"}
					onClick={() => setStatusFilter("new")}
				>
					{__("New", "jcore-turva")}
				</Button>
				<Button
					variant={statusFilter === "archived" ? "primary" : "secondary"}
					onClick={() => setStatusFilter("archived")}
				>
					{__("Archived", "jcore-turva")}
				</Button>
			</div>

			{error && <p className="jcore-turva__error">{error}</p>}

			{reports === null && !error && <Spinner />}

			{reports !== null && reports.length === 0 && (
				<p className="jcore-turva__empty">
					{statusFilter === "new"
						? __("No new violation reports.", "jcore-turva")
						: __("No archived reports.", "jcore-turva")}
				</p>
			)}

			{reports !== null && reports.length > 0 && (
				<table className="jcore-turva__reports-table widefat striped">
					<thead>
						<tr>
							<th className="jcore-turva__col-processed" />
							<th>{__("Directive", "jcore-turva")}</th>
							<th>{__("Blocked URI", "jcore-turva")}</th>
							<th>{__("Document", "jcore-turva")}</th>
							<th className="jcore-turva__col-count">
								{__("Count", "jcore-turva")}
							</th>
							<th className="jcore-turva__col-date">
								{__("Last seen", "jcore-turva")}
							</th>
							<th className="jcore-turva__col-actions">
								{__("Actions", "jcore-turva")}
							</th>
						</tr>
					</thead>
					<tbody>
						{reports.map((report) => (
							<tr
								key={report.id}
								className={
									report.processed
										? "jcore-turva__report-row is-processed"
										: "jcore-turva__report-row"
								}
							>
								<td className="jcore-turva__col-processed">
									<Button
										icon={check}
										isPressed={report.processed}
										label={
											report.processed
												? __("Unmark as processed", "jcore-turva")
												: __("Mark as processed", "jcore-turva")
										}
										size="small"
										variant="tertiary"
										onClick={() =>
											handleToggleProcessed(report.id, !report.processed)
										}
									/>
								</td>
								<td>
									<code>{report.violated_directive}</code>
								</td>
								<td
									className="jcore-turva__uri-cell"
									title={report.blocked_uri}
								>
									<code>{report.blocked_uri}</code>
								</td>
								<td
									className="jcore-turva__uri-cell"
									title={report.document_uri}
								>
									<code>{report.document_uri}</code>
									{report.uris?.length > 1 && (
										<span className="jcore-turva__uri-count">
											{" "}
											({report.uris.length})
										</span>
									)}
								</td>
								<td>{report.report_count}</td>
								<td>{formatDate(report.last_seen)}</td>
								<td className="jcore-turva__report-actions">
									{statusFilter === "new" ? (
										<>
											<Button
												variant="secondary"
												size="small"
												onClick={() => setReviewReport(report)}
											>
												{__("Review", "jcore-turva")}
											</Button>
											<Button
												variant="tertiary"
												size="small"
												isDestructive
												onClick={() => handleArchive(report.id)}
											>
												{__("Archive", "jcore-turva")}
											</Button>
										</>
									) : (
										<>
											<Button
												variant="secondary"
												size="small"
												onClick={() => handleUnarchive(report.id)}
											>
												{__("Restore", "jcore-turva")}
											</Button>
											<Button
												variant="tertiary"
												size="small"
												isDestructive
												onClick={() => handleDelete(report.id)}
											>
												{__("Delete", "jcore-turva")}
											</Button>
										</>
									)}
								</td>
							</tr>
						))}
					</tbody>
				</table>
			)}

			{reviewReport && (
				<ReviewModal
					report={reviewReport}
					onClose={() => setReviewReport(null)}
					onReviewed={handleReviewed}
				/>
			)}
		</div>
	);
}
