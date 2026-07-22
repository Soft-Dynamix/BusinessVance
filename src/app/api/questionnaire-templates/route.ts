import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const include = searchParams.get('include');
    const status = searchParams.get('status');

    const where: Record<string, unknown> = {};
    if (status && status !== 'all') where.status = status;

    const templates = await db.questionnaireTemplate.findMany({
      where,
      include: {
        sections: {
          include: { questions: { orderBy: { displayOrder: 'asc' } } },
          orderBy: { displayOrder: 'asc' },
        },
        ...(include === 'services' ? { services: true } : {}),
        _count: { select: { services: true, projects: true } },
      },
      orderBy: { updatedAt: 'desc' },
    });

    return NextResponse.json({ templates });
  } catch (error) {
    console.error('Error fetching questionnaire templates:', error);
    return NextResponse.json({ error: 'Failed to fetch templates' }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { name, description, version, status, sections } = body;

    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    const template = await db.questionnaireTemplate.create({
      data: {
        name,
        slug,
        description: description || '',
        version: version || '1.0',
        status: status || 'draft',
        sections: sections ? {
          create: (sections as Array<{ title: string; description?: string; isShared?: boolean; questions?: Array<{ type: string; label: string; placeholder?: string; required?: boolean; options?: string; helpText?: string }> }>).map((s, sIdx) => ({
            title: s.title,
            description: s.description || '',
            displayOrder: sIdx,
            isShared: s.isShared || false,
            questions: s.questions ? {
              create: s.questions.map((q, qIdx) => ({
                type: q.type || 'text',
                label: q.label,
                placeholder: q.placeholder || '',
                required: q.required || false,
                options: q.options || '[]',
                helpText: q.helpText || '',
                displayOrder: qIdx,
              })),
            } : undefined,
          })),
        } : undefined,
      },
      include: {
        sections: { include: { questions: { orderBy: { displayOrder: 'asc' } } }, orderBy: { displayOrder: 'asc' } },
        _count: { select: { services: true, projects: true } },
      },
    });

    return NextResponse.json({ template }, { status: 201 });
  } catch (error) {
    console.error('Error creating questionnaire template:', error);
    return NextResponse.json({ error: 'Failed to create template' }, { status: 500 });
  }
}