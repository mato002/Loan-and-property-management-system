from pathlib import Path
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT, TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak,
    KeepTogether, HRFlowable,
)


OUT = Path("output/pdf/passion-homes-landlord-statements-requirements.pdf")
OUT.parent.mkdir(parents=True, exist_ok=True)

INK = colors.HexColor("#17324D")
GREEN = colors.HexColor("#07866C")
PALE = colors.HexColor("#EAF5F2")
PALE_BLUE = colors.HexColor("#EEF3F8")
MUTED = colors.HexColor("#526579")
AMBER = colors.HexColor("#FFF4D6")
LINE = colors.HexColor("#D7E0E8")

doc = SimpleDocTemplate(
    str(OUT), pagesize=A4,
    rightMargin=18 * mm, leftMargin=18 * mm,
    topMargin=18 * mm, bottomMargin=18 * mm,
    title="Passion Homes | Landlord Statements - Requirements Report",
    author="Passion Homes requirements summary",
    subject="Landlord settlement arrangements and system requirements",
)

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(
    name="Eyebrow", parent=styles["Normal"], fontName="Helvetica-Bold",
    fontSize=8, leading=11, textColor=GREEN, spaceAfter=5,
))
styles.add(ParagraphStyle(
    name="TitleCustom", parent=styles["Title"], fontName="Helvetica-Bold",
    fontSize=25, leading=29, textColor=INK, alignment=TA_LEFT,
    spaceAfter=8,
))
styles.add(ParagraphStyle(
    name="Deck", parent=styles["Normal"], fontName="Helvetica",
    fontSize=11, leading=16, textColor=MUTED, spaceAfter=12,
))
styles.add(ParagraphStyle(
    name="H1Custom", parent=styles["Heading1"], fontName="Helvetica-Bold",
    fontSize=16, leading=20, textColor=INK, spaceBefore=7, spaceAfter=8,
))
styles.add(ParagraphStyle(
    name="H2Custom", parent=styles["Heading2"], fontName="Helvetica-Bold",
    fontSize=11, leading=14, textColor=GREEN, spaceBefore=5, spaceAfter=4,
))
styles.add(ParagraphStyle(
    name="BodyCustom", parent=styles["BodyText"], fontName="Helvetica",
    fontSize=9.2, leading=13.2, textColor=INK, spaceAfter=5,
))
styles.add(ParagraphStyle(
    name="SmallCustom", parent=styles["BodyText"], fontName="Helvetica",
    fontSize=8, leading=10.5, textColor=MUTED,
))
styles.add(ParagraphStyle(
    name="CardTitle", parent=styles["BodyText"], fontName="Helvetica-Bold",
    fontSize=10, leading=13, textColor=INK, spaceAfter=3,
))
styles.add(ParagraphStyle(
    name="TableHeadCustom", parent=styles["BodyText"], fontName="Helvetica-Bold",
    fontSize=8, leading=10, textColor=colors.white,
))
styles.add(ParagraphStyle(
    name="TableBodyCustom", parent=styles["BodyText"], fontName="Helvetica",
    fontSize=8, leading=10.5, textColor=INK,
))
styles.add(ParagraphStyle(
    name="Callout", parent=styles["BodyText"], fontName="Helvetica-Bold",
    fontSize=9.3, leading=13.5, textColor=INK,
))

P = lambda text, style="BodyCustom": Paragraph(text, styles[style])

def bullet(text):
    return Paragraph("•&nbsp;&nbsp;" + text, styles["BodyCustom"])

def footer(canvas, document):
    canvas.saveState()
    w, _ = A4
    canvas.setStrokeColor(LINE)
    canvas.setLineWidth(0.6)
    canvas.line(18 * mm, 13 * mm, w - 18 * mm, 13 * mm)
    canvas.setFont("Helvetica", 7.5)
    canvas.setFillColor(MUTED)
    canvas.drawString(18 * mm, 8.5 * mm, "PASSION HOMES  |  LANDLORD STATEMENTS REQUIREMENTS")
    canvas.drawRightString(w - 18 * mm, 8.5 * mm, f"{document.page}")
    canvas.restoreState()

story = []
story += [
    P("REQUIREMENTS REPORT  /  LANDLORD ACCOUNTING", "Eyebrow"),
    P("Landlord statements &amp;<br/>settlement rules", "TitleCustom"),
    P("A requirements summary for configuring landlord-specific rent, service-charge, expense and commission arrangements in Passion Homes.", "Deck"),
]

status = Table([[P("IMPLEMENTATION STATUS", "Eyebrow"), P("Requested capability; not implemented yet", "Callout")]], colWidths=[43 * mm, 125 * mm])
status.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), AMBER),
    ("BOX", (0, 0), (-1, -1), 0.7, colors.HexColor("#E8C96E")),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ("LEFTPADDING", (0, 0), (-1, -1), 10),
    ("RIGHTPADDING", (0, 0), (-1, -1), 10),
    ("TOPPADDING", (0, 0), (-1, -1), 8),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
]))
story += [status, Spacer(1, 8 * mm), P("Purpose", "H1Custom"),
          P("Passion Homes has landlords with different agreements. The system needs to apply each landlord's agreed rules when preparing statements and calculating what is payable. A single fixed settlement formula will not represent all cases.")]

story += [P("The four settlement arrangements", "H1Custom")]

arrangements = [
    ("01  Guaranteed rent, vacancy risk carried by management", "The landlord is paid the agreed rent for all units in the property, less the agreed commission, even when a unit is vacant or rent has not been collected. The statement must distinguish rent due from rent actually received and show any collection shortfall funded or carried by Passion Homes."),
    ("02  Rent and service collections, expenses paid before settlement", "Passion Homes collects rent and specified items such as garbage or utilities, pays the relevant service providers, deducts those expenses and the agreed commission, then pays the landlord. Some landlords also require advance payments that are not conditional on occupancy; those advances must be recorded and shown separately."),
    ("03  Collections passed to landlord for self-managed expenses", "Passion Homes collects rent and agreed service charges and passes those collections to the landlord. The landlord pays service providers directly. Passion Homes deducts only its agreed commission; the statement must make clear that the service collections were passed through and were not paid to providers by Passion Homes."),
    ("04  Rent-only collection and settlement", "Passion Homes collects rent, deducts the agreed commission and pays the landlord's share. Garbage, utilities and other service-provider amounts are outside this settlement arrangement unless explicitly added to the agreement."),
]

for title, body in arrangements:
    card = Table([[P(title, "CardTitle")], [P(body)]], colWidths=[168 * mm])
    card.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), PALE_BLUE),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("LINEBEFORE", (0, 0), (0, -1), 3, GREEN),
        ("LEFTPADDING", (0, 0), (-1, -1), 10),
        ("RIGHTPADDING", (0, 0), (-1, -1), 10),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]))
    story += [KeepTogether([card, Spacer(1, 3 * mm)])]

story += [PageBreak(), P("What the system needs to support", "H1Custom")]

reqs = [
    ("Agreement setup", "Store the settlement arrangement against the landlord and property. Allow the covered properties and charge types to be specified. Where one landlord has different agreements across properties, retain a separate rule set for each property."),
    ("Collection and vacancy treatment", "Track rent and service charges billed, collected, outstanding and not collected. For guaranteed-rent agreements, calculate the landlord's contractual entitlement separately from the amount collected and show vacancy/collection shortfalls explicitly."),
    ("Service-provider handling", "For each garbage, utility or other service amount, record whether it is collected, who is responsible for paying the provider, what was paid, and whether it is deducted before the landlord settlement or passed through to the landlord."),
    ("Commission configuration", "Record the commission rate or amount and its calculation basis for the agreement. The calculation must use the agreed basis (for example, rent collected or another agreed amount) and show the commission as a separate statement line."),
    ("Advances and recurring obligations", "Record advances with amount, date, recipient or purpose, covered period, and recovery or offset treatment. Where an advance is payable regardless of occupancy, do not silently make it dependent on rent collection; show any resulting funding shortfall."),
    ("Statement calculation and traceability", "Generate a transparent property-level statement showing opening balance, rent and other collections, vacancy or collection shortfall where applicable, provider payments, commission, advances, adjustments and net amount due to the landlord. Each total should be traceable to its source transactions."),
    ("Review and reconciliation", "Provide a preview before posting or payment. Preserve the applied agreement and calculation details on each statement period so that a later rule change does not rewrite previously issued statements."),
]

for heading, body in reqs:
    story += [P(heading, "H2Custom"), P(body)]

story += [P("Illustrative statement logic", "H1Custom")]
logic_data = [
    [P("Arrangement", "TableHeadCustom"), P("Settlement basis", "TableHeadCustom"), P("Key statement treatment", "TableHeadCustom")],
    [P("Guaranteed rent", "TableBodyCustom"), P("Agreed rent entitlement", "TableBodyCustom"), P("Show entitlement even if collection is lower; expose vacancy/uncollected shortfall.", "TableBodyCustom")],
    [P("Provider expenses deducted", "TableBodyCustom"), P("Collected amounts less provider payments and commission", "TableBodyCustom"), P("List provider payments and calculate landlord net after agreed deductions.", "TableBodyCustom")],
    [P("Landlord pays providers", "TableBodyCustom"), P("Collections less commission", "TableBodyCustom"), P("Show service collections passed to landlord; no provider payment by Passion Homes.", "TableBodyCustom")],
    [P("Rent only", "TableBodyCustom"), P("Rent collected less commission", "TableBodyCustom"), P("Exclude service charges unless included in that property's agreement.", "TableBodyCustom")],
]
logic = Table(logic_data, colWidths=[34 * mm, 53 * mm, 81 * mm], repeatRows=1)
logic.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), INK),
    ("BACKGROUND", (0, 1), (-1, -1), colors.white),
    ("GRID", (0, 0), (-1, -1), 0.45, LINE),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 6),
    ("RIGHTPADDING", (0, 0), (-1, -1), 6),
    ("TOPPADDING", (0, 0), (-1, -1), 6),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
]))
story += [logic, Spacer(1, 4 * mm), P("The final calculation order must follow the specific agreement recorded for each landlord/property; these examples describe required flexibility, not a universal formula.", "SmallCustom")]

story += [PageBreak(), P("Decisions to confirm before implementation", "H1Custom")]
story += [P("These details determine how the rules should calculate and how exceptions should be displayed:")]
for item in [
    "Is commission calculated on rent collected, rent contractually due, or another agreed base? Can the basis vary by landlord or property?",
    "For guaranteed rent, who funds the difference when vacancy or non-payment means collections are below the agreed entitlement, and how should that funding appear in accounts?",
    "For advance payments, are they recoverable from future collections, treated as an expense, or posted to a separate landlord advance balance?",
    "Should garbage, utilities and similar charges be treated as collections, pass-through amounts, provider expenses, or a combination depending on the agreement?",
    "Can a landlord use different arrangements for different properties, or can the arrangement change by period? What approval and effective-date controls are required?",
    "How should arrears, refunds, reversals, partial collections and prior-period corrections affect a landlord statement that has already been reviewed or paid?",
]:
    story.append(bullet(item))

story += [Spacer(1, 4 * mm), P("Recommended workflow", "H1Custom")]
workflow = [
    [P("1", "TableHeadCustom"), P("Configure", "CardTitle"), P("Choose the landlord/property settlement arrangement, eligible collections, expense responsibility, commission rule and advance terms.", "TableBodyCustom")],
    [P("2", "TableHeadCustom"), P("Preview", "CardTitle"), P("Calculate a period statement and show each source amount, deduction, shortfall and net payable before it is posted.", "TableBodyCustom")],
    [P("3", "TableHeadCustom"), P("Reconcile", "CardTitle"), P("Link collections, provider payments and advances to the statement so the balances can be verified.", "TableBodyCustom")],
    [P("4", "TableHeadCustom"), P("Issue and retain", "CardTitle"), P("Issue the statement and preserve its rules, calculations and transaction links for later review.", "TableBodyCustom")],
]
wf = Table(workflow, colWidths=[10 * mm, 30 * mm, 128 * mm])
wf.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (0, -1), GREEN),
    ("BACKGROUND", (1, 0), (-1, -1), PALE),
    ("GRID", (0, 0), (-1, -1), 0.45, LINE),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ("LEFTPADDING", (0, 0), (-1, -1), 6),
    ("RIGHTPADDING", (0, 0), (-1, -1), 6),
    ("TOPPADDING", (0, 0), (-1, -1), 7),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
]))
story += [wf, Spacer(1, 6 * mm)]

status2 = Table([[P("CURRENT STATE", "Eyebrow"), P("These landlord-specific settlement rules have been requested but have not yet been implemented. Existing landlord statements should not be assumed to apply all four arrangements automatically.", "Callout")]], colWidths=[35 * mm, 133 * mm])
status2.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), AMBER),
    ("BOX", (0, 0), (-1, -1), 0.7, colors.HexColor("#E8C96E")),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ("LEFTPADDING", (0, 0), (-1, -1), 9),
    ("RIGHTPADDING", (0, 0), (-1, -1), 9),
    ("TOPPADDING", (0, 0), (-1, -1), 8),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
]))
story += [status2, Spacer(1, 3 * mm), P("Prepared from the landlord settlement workflows described by Passion Homes. This report captures business requirements for system alignment; it is not a statement of completed software functionality.", "SmallCustom")]

doc.build(story, onFirstPage=footer, onLaterPages=footer)
print(OUT.resolve())
