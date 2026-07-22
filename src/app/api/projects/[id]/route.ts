import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function GET(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id } = await params;
    const project = await db.project.findUnique({
      where: { id },
      include: {
        services: true,
        agreements: { include: { template: true } },
        questionnaires: {
          include: {
            template: { include: { sections: { include: { questions: { orderBy: { displayOrder: 'asc' } } }, orderBy: { displayOrder: 'asc' } } } },
            responses: true,
          },
        },
        documents: true,
      },
    });

    if (!project) return NextResponse.json({ error: 'Project not found' }, { status: 404 });
    return NextResponse.json({ project });
  } catch (error) {
    console.error('Error fetching project:', error);
    return NextResponse.json({ error: 'Failed to fetch project' }, { status: 500 });
  }
}

export async function PUT(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id } = await params;
    const body = await request.json();
    const { services, agreements, questionnaires, documents, ...data } = body;

    // Handle status-based progress auto-calculation
    const statusProgress: Record<string, number> = {
      'awaiting-agreement': 10,
      'awaiting-questionnaire': 25,
      'awaiting-documents': 50,
      'information-review': 60,
      'in-progress': 75,
      'quality-check': 90,
      'completed': 100,
      'delivered': 100,
      'archived': 100,
    };
    if (data.status && !data.progressPercent) {
      data.progressPercent = statusProgress[data.status] || 0;
    }

    const project = await db.project.update({
      where: { id },
      data,
      include: {
        services: true,
        agreements: { include: { template: { select: { name: true } } } },
        questionnaires: { include: { template: { select: { name: true } }, _count: { select: { responses: true } } } },
        documents: true,
      },
    });

    return NextResponse.json({ project });
  } catch (error) {
    console.error('Error updating project:', error);
    return NextResponse.json({ error: 'Failed to update project' }, { status: 500 });
  }
}

export async function DELETE(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id } = await params;
    await db.project.delete({ where: { id } });
    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Error deleting project:', error);
    return NextResponse.json({ error: 'Failed to delete project' }, { status: 500 });
  }
}