import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function GET(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id } = await params;
    const template = await db.questionnaireTemplate.findUnique({
      where: { id },
      include: {
        sections: { include: { questions: { orderBy: { displayOrder: 'asc' } } }, orderBy: { displayOrder: 'asc' } },
        services: true,
        _count: { select: { projects: true } },
      },
    });

    if (!template) return NextResponse.json({ error: 'Template not found' }, { status: 404 });
    return NextResponse.json({ template });
  } catch (error) {
    console.error('Error fetching template:', error);
    return NextResponse.json({ error: 'Failed to fetch template' }, { status: 500 });
  }
}

export async function PUT(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id } = await params;
    const body = await request.json();
    const { sections, ...data } = body;

    if (data.name) {
      data.slug = data.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    }

    // If sections are provided, rebuild them
    if (sections !== undefined) {
      // Delete existing sections and their questions
      const existingSections = await db.questionnaireSection.findMany({ where: { templateId: id } });
      for (const s of existingSections) {
        await db.questionnaireQuestion.deleteMany({ where: { sectionId: s.id } });
      }
      await db.questionnaireSection.deleteMany({ where: { templateId: id } });

      // Create new sections with questions
      data.sections = {
        create: (sections as Array<Record<string, unknown>>).map((s: Record<string, unknown>, sIdx: number) => ({
          title: String(s.title),
          description: String(s.description || ''),
          displayOrder: sIdx,
          isShared: Boolean(s.isShared),
          questions: {
            create: (s.questions as Array<Record<string, unknown>> || []).map((q: Record<string, unknown>, qIdx: number) => ({
              type: String(q.type || 'text'),
              label: String(q.label),
              placeholder: String(q.placeholder || ''),
              required: Boolean(q.required),
              options: typeof q.options === 'string' ? q.options : JSON.stringify(q.options || []),
              helpText: String(q.helpText || ''),
              displayOrder: qIdx,
            })),
          },
        })),
      };
    }

    const template = await db.questionnaireTemplate.update({
      where: { id },
      data,
      include: {
        sections: { include: { questions: { orderBy: { displayOrder: 'asc' } } }, orderBy: { displayOrder: 'asc' } },
        _count: { select: { services: true, projects: true } },
      },
    });

    return NextResponse.json({ template });
  } catch (error) {
    console.error('Error updating template:', error);
    return NextResponse.json({ error: 'Failed to update template' }, { status: 500 });
  }
}

export async function DELETE(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id } = await params;
    await db.questionnaireTemplate.delete({ where: { id } });
    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Error deleting template:', error);
    return NextResponse.json({ error: 'Failed to delete template' }, { status: 500 });
  }
}