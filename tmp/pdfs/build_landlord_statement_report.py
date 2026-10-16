from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT, TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, KeepTogether
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase import pdfmetrics
from reportlab.lib.colors import HexColor

OUT = r"output/pdf/passion-homes-landlord-statements-requirements-assessment.pdf"
NAVY = HexColor('#123047')
TEAL = HexColor('#087F70')
PALE = HexColor('#EAF4F3')
LIGHT = HexColor('#F3F6F8')
INK = HexColor('#233746')
MUTED = HexColor('#5C6D78')
AMBER = HexColor('#FFF4DB')

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name='Kicker', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=8, leading=11, textColor=TEAL, spaceAfter=5))
styles.add(ParagraphStyle(name='TitleCustom', parent=styles['Title'], fontName='Helvetica-Bold', fontSize=23, leading=27, textColor=NAVY, alignment=TA_LEFT, spaceAfter=8))
styles.add(ParagraphStyle(name='Subtitle', parent=styles['Normal'], fontName='Helvetica', fontSize=10, leading=14, textColor=MUTED, spaceAfter=13))
styles.add(ParagraphStyle(name='H1Custom', parent=styles['Heading1'], fontName='Helvetica-Bold', fontSize=14, leading=18, textColor=NAVY, spaceBefore=5, spaceAfter=3, keepWithNext=True))
styles.add(ParagraphStyle(name='H2Custom', parent=styles['Heading2'], fontName='Helvetica-Bold', fontSize=10.5, leading=14, textColor=TEAL, spaceBefore=5, spaceAfter=3, keepWithNext=True))
styles.add(ParagraphStyle(name='BodyCustom', parent=styles['BodyText'], fontName='Helvetica', fontSize=8.7, leading=11.6, textColor=INK, spaceAfter=6))
styles.add(ParagraphStyle(name='SmallCustom', parent=styles['BodyText'], fontName='Helvetica', fontSize=7.8, leading=9.7, textColor=INK))
styles.add(ParagraphStyle(name='CellHead', parent=styles['BodyText'], fontName='Helvetica-Bold', fontSize=8, leading=10, textColor=colors.white))
styles.add(ParagraphStyle(name='Cell', parent=styles['BodyText'], fontName='Helvetica', fontSize=7.3, leading=9.1, textColor=INK))
styles.add(ParagraphStyle(name='Callout', parent=styles['BodyText'], fontName='Helvetica-Bold', fontSize=9.2, leading=13, textColor=NAVY))

P = lambda text, style='BodyCustom': Paragraph(text, styles[style])

def footer(canvas, doc):
    canvas.saveState()
    w, h = A4
    canvas.setStrokeColor(HexColor('#D6E0E5'))
    canvas.setLineWidth(.5)
    canvas.line(18*mm, 15*mm, w-18*mm, 15*mm)
    canvas.setFont('Helvetica', 7.5)
    canvas.setFillColor(MUTED)
    canvas.drawString(18*mm, 10.5*mm, 'Passion Homes | Landlord statements requirements assessment')
    canvas.drawRightString(w-18*mm, 10.5*mm, f'Page {doc.page}')
    canvas.restoreState()

doc = SimpleDocTemplate(OUT, pagesize=A4, rightMargin=16*mm, leftMargin=16*mm, topMargin=14*mm, bottomMargin=19*mm, title='Passion Homes Landlord Statements - Requirements Assessment', author='Passion Homes')
story=[]
story += [P('REQUIREMENTS ASSESSMENT  /  LANDLORD SETTLEMENTS','Kicker'), P('Landlord Statements','TitleCustom'), P('Passion Homes requirements compared with the current system','Subtitle')]
meta = Table([[P('<b>Prepared for</b><br/>Passion Homes','SmallCustom'), P('<b>Purpose</b><br/>Review current coverage and define the changes needed','SmallCustom'), P('<b>Date</b><br/>16 October 2026','SmallCustom')]], colWidths=[52*mm, 88*mm, 34*mm])
meta.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),LIGHT),('BOX',(0,0),(-1,-1),.5,HexColor('#D6E0E5')),('INNERGRID',(0,0),(-1,-1),.5,colors.white),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),9),('RIGHTPADDING',(0,0),(-1,-1),8),('TOPPADDING',(0,0),(-1,-1),8),('BOTTOMPADDING',(0,0),(-1,-1),8)]))
story += [meta, Spacer(1,10), P('Executive summary','H1Custom'), P('Passion Homes has several different landlord agreements. The current system includes useful settlement components, including ownership percentages, commission rates, payment collection records, landlord ledger entries, payouts, and manual advances. However, it does not yet let staff select and apply each landlord\'s complete agreement when preparing a statement. As a result, some statements can show collections without calculating the landlord entitlement expected under the agreement, or can apply commission to charges that should be excluded.','BodyCustom')]
callout = Table([[P('Assessment: the current system is a foundation, but the four operating arrangements are not fully covered. A landlord/property-level agreement and a consistent settlement calculation are needed.','Callout')]], colWidths=[174*mm])
callout.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),PALE),('BOX',(0,0),(-1,-1),.7,TEAL),('LEFTPADDING',(0,0),(-1,-1),10),('RIGHTPADDING',(0,0),(-1,-1),10),('TOPPADDING',(0,0),(-1,-1),8),('BOTTOMPADDING',(0,0),(-1,-1),8)]))
story += [callout, P('Passion Homes operating arrangements','H1Custom')]
scenarios = [
('1. Agreed rent, including vacant units', 'Pay the landlord the agreed rent for all units, even when a unit is vacant or rent has not been collected, less the agreed commission.'),
('2. Agency pays service providers', 'Collect rent and service charges, pay the relevant providers, deduct those costs and commission, then pay the landlord. Some landlords may also receive advances when units are vacant.'),
('3. Landlord pays service providers', 'Collect rent and service charges and pass them to the landlord. The landlord pays providers; Passion Homes deducts its commission.'),
('4. Rent-only settlement', 'Collect rent, deduct commission, and pay the landlord their share. Service charges are handled separately or excluded from the rent settlement.'),
]
for title, body in scenarios:
    story.append(KeepTogether([P(title,'H2Custom'),P(body,'BodyCustom')]))
story += [P('Current system coverage','H1Custom')]

data=[[P('Arrangement','CellHead'),P('Current coverage','CellHead'),P('Main gap','CellHead')]]
rows=[
('Agreed rent including vacancies','Not covered as described. Current settlement income is based on completed payments allocated to invoices.','No guaranteed or scheduled rent entitlement is calculated for vacant units.'),
('Agency pays provider costs and may advance funds','Partly covered. Ledger debits and manual landlord advances exist.','The landlord agreement does not define which costs are deducted or set automatic advance rules.'),
('Landlord pays providers; agency passes through collections','Partly covered. Collections can be reported.','No setting controls provider responsibility, service-charge pass-through, or commissionable categories.'),
('Rent-only settlement','Partly covered. Actual collections are available.','Commission is not selectable by income category; service charges may be included in the commission base.'),
]
for a,b,c in rows:
    data.append([P(a,'Cell'),P(b,'Cell'),P(c,'Cell')])
t=Table(data,colWidths=[42*mm,66*mm,66*mm],repeatRows=1,hAlign='LEFT')
t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),NAVY),('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white,LIGHT]),('GRID',(0,0),(-1,-1),.45,HexColor('#D4DEE4')),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),6),('RIGHTPADDING',(0,0),(-1,-1),6),('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6)]))
story.append(t)
story += [P('How current calculations work','H1Custom'), P('For the property/landlord settlement, collection totals are drawn from completed payments allocated to invoices within the selected payment-date period. Amounts are grouped into rent, garbage, water, and other charges. The landlord ownership percentage is applied to those collections, and the configured property commission rate is applied to the landlord share.','BodyCustom'), P('The settlement closing balance is calculated from the landlord ledger: opening balance plus ledger credits, less ledger debits and remittances. This means collection totals and the amount due are related but distinct. A payment can appear in collections without increasing the amount due if the matching landlord-ledger credit has not been posted.','BodyCustom'), P('The landlord summary report also aggregates completed payment allocations and posted property expenses. It calculates commission on the owner share of collected amounts and subtracts the landlord\'s share of expenses. This summary is not a substitute for a settlement agreement that defines how each landlord is paid.','BodyCustom')]
story += [P('Recommended system changes','H1Custom'), P('Record a settlement agreement for each landlord/property relationship. The agreement should be the source of truth for statement calculations and should support the following settings:','BodyCustom')]
settings=[
('Settlement basis','Actual rent collected, or agreed rent entitlement for all units.'),
('Vacancy treatment','Whether vacant units accrue rent payable to the landlord.'),
('Included income','Rent, garbage, water, and other charge categories.'),
('Provider responsibility','Pass charges through to the landlord, or record and deduct costs paid by Passion Homes.'),
('Commission rules','Rate and commissionable categories, for example rent only or all collected income.'),
('Advances','Whether advances are allowed, amount or calculation, frequency, and approval requirements.'),
]
settings_data=[[P('Setting','CellHead'),P('Required agreement detail','CellHead')]]+[[P(x,'Cell'),P(y,'Cell')] for x,y in settings]
st=Table(settings_data,colWidths=[43*mm,131*mm],repeatRows=1)
st.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),TEAL),('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white,LIGHT]),('GRID',(0,0),(-1,-1),.45,HexColor('#D4DEE4')),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),7),('RIGHTPADDING',(0,0),(-1,-1),7),('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6)]))
story.append(st)
story += [P('Statement presentation and controls','H1Custom'), P('Each statement should show separate, traceable lines for agreed rent entitlement, actual collections, service charges collected, provider expenses paid, commission, advances, previous payouts, and closing balance. For guaranteed-rent agreements, show the landlord\'s accrued entitlement separately from cash collected so the statement does not imply that uncollected rent is cash already received.','BodyCustom'), P('Calculations should use the recorded agreement for the statement period and retain an audit trail when an agreement changes. Staff should be able to see why a figure was included, excluded, or deducted. Payout controls should also show both the amount owed and the cash available for payment.','BodyCustom'), P('Conclusion','H1Custom'), P('Passion Homes needs landlord statements that follow the contract for each landlord, rather than one collection-and-commission formula for everyone. The current system does not fully support the four arrangements described. Adding landlord/property-level settlement agreements and using them consistently in the ledger, reports, and payout workflow will make statements accurate, explainable, and easier to reconcile.','BodyCustom')]
doc.build(story,onFirstPage=footer,onLaterPages=footer)
print(OUT)

