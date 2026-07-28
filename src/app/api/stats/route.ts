import { db } from '@/lib/db'
import { NextResponse } from 'next/server'

export async function GET() {
  try {
    const [services, plans, categories, projects, questionnaires, agreements, icons] = await Promise.all([
      db.service.count(),
      db.plan.count(),
      db.category.count(),
      db.project.count(),
      db.questionnaireTemplate.count(),
      db.agreementTemplate.count(),
      db.icon.count(),
    ])
    return NextResponse.json({ stats: { services, plans, categories, projects, questionnaires, agreements, icons } })
  } catch (error) {
    return NextResponse.json({ error: 'Failed to fetch stats' }, { status: 500 })
  }
}
