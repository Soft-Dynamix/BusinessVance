import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function POST(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id } = await params;
    const body = await request.json();
    const { templateId, responses, status } = body;

    if (!templateId) {
      return NextResponse.json({ error: 'Template ID is required' }, { status: 400 });
    }

    // Find or create project questionnaire
    let pq = await db.projectQuestionnaire.findFirst({
      where: { projectId: id, templateId },
    });

    if (!pq) {
      pq = await db.projectQuestionnaire.create({
        data: { projectId: id, templateId, status: status || 'in-progress' },
      });
    } else if (status) {
      await db.projectQuestionnaire.update({
        where: { id: pq.id },
        data: {
          status,
          ...(status === 'completed' ? { completedAt: new Date() } : {}),
        },
      });
    }

    // Save responses (delete old ones for this questionnaire, insert new)
    if (responses && Array.isArray(responses)) {
      await db.projectResponse.deleteMany({ where: { questionnaireId: pq.id } });
      await db.projectResponse.createMany({
        data: responses.map((r: { questionId: string; value: string }) => ({
          questionnaireId: pq.id,
          questionId: r.questionId,
          value: String(r.value || ''),
        })),
      });
    }

    // Update project progress
    const allQs = await db.projectQuestionnaire.findMany({ where: { projectId: id } });
    const completed = allQs.filter(q => q.status === 'completed').length;
    const total = allQs.length;
    const hasAgreement = await db.projectAgreement.findFirst({ where: { projectId: id } });
    const hasDocs = await db.projectDocument.findFirst({ where: { projectId: id } });

    let progress = 0;
    if (hasAgreement) progress += 15;
    if (total > 0) progress += Math.round((completed / total) * 50);
    if (hasDocs) progress += 10;
    progress = Math.min(progress, 100);

    const projectStatus = completed === total && total > 0 ? 'awaiting-documents' : 'awaiting-questionnaire';
    await db.project.update({
      where: { id },
      data: { progressPercent: progress, ...(completed === total && total > 0 ? { status: projectStatus } : {}) },
    });

    return NextResponse.json({ success: true, questionnaireId: pq.id });
  } catch (error) {
    console.error('Error saving questionnaire:', error);
    return NextResponse.json({ error: 'Failed to save questionnaire' }, { status: 500 });
  }
}