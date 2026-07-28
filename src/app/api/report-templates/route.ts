import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function GET() {
  try {
    const templates = await db.reportTemplate.findMany({
      include: { _count: { select: { reports: true } } },
      orderBy: { updatedAt: 'desc' },
    });
    return NextResponse.json({ templates });
  } catch (error) {
    console.error('Error fetching report templates:', error);
    return NextResponse.json({ error: 'Failed to fetch templates' }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { name, description, content, version, status } = body;
    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    const template = await db.reportTemplate.create({
      data: {
        name,
        slug,
        description: description || '',
        content: content || '',
        version: version || '1.0',
        status: status || 'draft',
      },
      include: { _count: { select: { reports: true } } },
    });

    return NextResponse.json({ template }, { status: 201 });
  } catch (error) {
    console.error('Error creating report template:', error);
    return NextResponse.json({ error: 'Failed to create template' }, { status: 500 });
  }
}
