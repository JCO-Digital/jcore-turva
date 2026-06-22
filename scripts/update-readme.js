const fs = require("fs");
const path = require("path");

const pluginFile = path.join(__dirname, "../jcore-turva.php");
const readmeFile = path.join(__dirname, "../README.md");

function getHeader(content, headerName) {
	const regex = new RegExp(`\\* ${headerName}:\\s*(.*)`, "i");
	const match = content.match(regex);
	return match ? match[1].trim() : "";
}

if (!fs.existsSync(pluginFile)) {
	console.error("Plugin file not found");
	process.exit(1);
}

const pluginContent = fs.readFileSync(pluginFile, "utf8");

const metadata = {
	"Requires at least": getHeader(pluginContent, "Requires at least"),
	"Tested up to": getHeader(pluginContent, "Tested up to"),
	"Requires PHP": getHeader(pluginContent, "Requires PHP"),
	"Stable tag": getHeader(pluginContent, "Version"),
	License: getHeader(pluginContent, "License"),
	"License URI": getHeader(pluginContent, "License URI"),
};

if (!fs.existsSync(readmeFile)) {
	console.error("README.md not found");
	process.exit(1);
}

let readmeContent = fs.readFileSync(readmeFile, "utf8");

// Update README headers
Object.entries(metadata).forEach(([key, value]) => {
	if (value) {
		const regex = new RegExp(`\\*\\*${key}:\\*\\*.*`, "g");
		if (readmeContent.match(regex)) {
			readmeContent = readmeContent.replace(regex, `**${key}:** ${value}  `);
		}
	}
});

// Update Requirements section
if (metadata["Requires at least"]) {
	readmeContent = readmeContent.replace(
		/- \*\*WordPress\*\*: .*/,
		`- **WordPress**: ${metadata["Requires at least"]} or higher`,
	);
}
if (metadata["Requires PHP"]) {
	readmeContent = readmeContent.replace(
		/- \*\*PHP\*\*: .*/,
		`- **PHP**: ${metadata["Requires PHP"]} or higher`,
	);
}

fs.writeFileSync(readmeFile, readmeContent);
console.log("README.md headers updated from jcore-turva.php");
